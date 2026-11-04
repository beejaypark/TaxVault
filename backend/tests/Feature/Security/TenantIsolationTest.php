<?php

namespace Tests\Feature\Security;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\Property;
use App\Domain\Reviews\Models\Review;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Another tenant's resource must behave exactly like a missing one,
 * and list endpoints must never leak.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $intruder;

    private FinancialYear $ownerYear;

    private FinancialYear $intruderYear;

    private Document $document;

    private Review $review;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
        Queue::fake();

        $this->owner = User::factory()->create();
        $this->intruder = User::factory()->create();
        $this->ownerYear = $this->year($this->owner);
        $this->intruderYear = $this->year($this->intruder);

        $type = DocumentType::query()->create([
            'code' => 'iso-'.Str::lower(Str::random(6)),
            'name' => 'Isolation',
            'description' => 'Isolation type',
            'metadata' => [],
            'status' => 'active',
        ]);

        Storage::disk('documents')->put('2026-27/secret.pdf', '%PDF-secret');
        $this->document = Document::query()->create([
            'user_id' => $this->owner->id,
            'financial_year_id' => $this->ownerYear->id,
            'document_type_id' => $type->id,
            'storage_disk' => 'documents',
            'object_key' => '2026-27/secret.pdf',
            'content_sha256' => hash('sha256', 'secret'),
            'original_filename' => 'secret.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 11,
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $extraction = DocumentExtraction::query()->create([
            'document_id' => $this->document->id,
            'provider' => 'test',
            'model' => 'test',
            'model_version' => '1',
            'extraction_version' => 'ocr-v1',
            'status' => 'completed',
            'correlation_id' => (string) Str::uuid(),
            'started_at' => now(),
            'completed_at' => now(),
            'output' => [],
            'quality_metadata' => [],
            'error_metadata' => null,
        ]);

        $this->review = Review::query()->create([
            'reviewer_id' => $this->owner->id,
            'document_id' => $this->document->id,
            'document_extraction_id' => $extraction->id,
            'review_type' => 'confidence',
            'status' => 'pending',
            'reviewer_notes' => 'owner only',
        ]);

        Transaction::query()->create([
            'user_id' => $this->owner->id,
            'financial_year_id' => $this->ownerYear->id,
            'transaction_date' => '2026-09-01',
            'description' => 'Owner secret expense',
            'direction' => 'expense',
            'amount' => '10.00',
            'currency' => 'AUD',
        ]);

        $this->property = Property::query()->create([
            'user_id' => $this->owner->id,
            'reference_code' => 'OWNER-PROP',
        ]);
    }

    private function year(User $user): FinancialYear
    {
        return FinancialYear::query()->create([
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
    }

    public function test_list_endpoints_never_leak_other_tenants_records(): void
    {
        foreach (['documents', 'reviews', 'transactions', 'properties', 'investments'] as $collection) {
            $response = $this->actingAs($this->intruder)->getJson("/api/{$collection}")->assertOk();
            $this->assertSame([], $response->json('data'), $collection);
        }

        $years = $this->actingAs($this->intruder)->getJson('/api/financial-years')->assertOk()->json('data');
        $this->assertNotContains($this->ownerYear->id, array_column($years, 'id'));
    }

    public function test_resource_endpoints_return_not_found_for_other_tenants(): void
    {
        $this->actingAs($this->intruder);

        $this->getJson("/api/documents/{$this->document->id}")->assertNotFound();
        $this->getJson("/api/documents/{$this->document->id}/content")->assertNotFound();
        $this->getJson("/api/reviews/{$this->review->id}")->assertNotFound();
        $this->postJson("/api/documents/{$this->document->id}/retry")->assertNotFound();
        $this->postJson("/api/reviews/{$this->review->id}/decision", ['decision' => 'reject'])->assertNotFound();
        $this->postJson("/api/reviews/{$this->review->id}/correction", ['field_path' => 'amount', 'value' => '1'])->assertNotFound();
        $this->deleteJson("/api/documents/{$this->document->id}")->assertNotFound();
        $this->deleteJson("/api/reviews/{$this->review->id}")->assertNotFound();

        $this->assertDatabaseHas('documents', ['id' => $this->document->id]);
        $this->assertDatabaseHas('reviews', ['id' => $this->review->id, 'status' => 'pending']);
        Storage::disk('documents')->assertExists('2026-27/secret.pdf');
    }

    public function test_cannot_reference_other_tenants_ids_in_request_bodies(): void
    {
        $this->actingAs($this->intruder);
        $type = DocumentType::query()->firstOrFail();

        $this->postJson('/api/documents', [
            'financial_year_id' => $this->ownerYear->id,
            'document_type_id' => $type->id,
            'file' => \Illuminate\Http\UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);

        $response = $this->postJson('/api/investments', [
            'property_id' => $this->property->id,
            'investment_type' => 'property',
            'acquisition_date' => '2026-07-01',
            'cost_base' => '100.00',
        ]);
        $this->assertContains($response->status(), [404, 422]);
        $this->assertDatabaseMissing('investments', ['property_id' => $this->property->id]);
    }

    public function test_transactions_are_created_in_the_callers_own_year(): void
    {
        $this->actingAs($this->intruder)
            ->postJson('/api/transactions', [
                'transaction_date' => '2026-09-15',
                'direction' => 'expense',
                'amount' => '5.00',
                'description' => 'Intruder expense',
                'financial_year_id' => $this->ownerYear->id,
                'user_id' => $this->owner->id,
                'document_id' => $this->document->id,
            ])->assertCreated();

        $created = Transaction::query()->where('description', 'Intruder expense')->sole();

        $this->assertSame($this->intruder->id, $created->user_id);
        $this->assertSame($this->intruderYear->id, $created->financial_year_id);
        $this->assertNull($created->document_id);
    }

    public function test_csv_export_only_contains_the_callers_data(): void
    {
        $this->actingAs($this->intruder)
            ->get('/api/exports/transactions.csv?financial_year_id='.$this->ownerYear->id)
            ->assertNotFound();

        $csv = $this->actingAs($this->intruder)
            ->get('/api/exports/transactions.csv?financial_year_id='.$this->intruderYear->id)
            ->assertOk()
            ->streamedContent();

        $this->assertStringNotContainsString('Owner secret expense', $csv);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        foreach ([
            '/api/documents', "/api/documents/{$this->document->id}", "/api/documents/{$this->document->id}/content",
            '/api/reviews', '/api/transactions', '/api/properties', '/api/investments',
            '/api/financial-years', '/api/inbound-email',
        ] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }
}
