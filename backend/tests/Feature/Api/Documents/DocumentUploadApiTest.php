<?php

namespace Tests\Feature\Api\Documents;

use App\Application\Documents\CreateDocument;
use App\Application\Documents\UploadDocument;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Jobs\ProcessUploadedDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DocumentUploadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_pdf_and_persist_server_metadata(): void
    {
        Storage::fake('documents');
        Queue::fake();

        [$user, $financialYear, $documentType] = $this->createDocumentContext();

        $contents = "%PDF-1.4\nTaxVault upload test\n%%EOF";
        $file = UploadedFile::fake()->createWithContent(
            'receipt.pdf',
            $contents,
        );

        $response = $this->actingAs($user)->post('/api/documents', [
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'file' => $file,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.financial_year_id', $financialYear->id)
            ->assertJsonPath('data.document_type_id', $documentType->id)
            ->assertJsonPath('data.original_filename', 'receipt.pdf')
            ->assertJsonPath('data.storage_disk', 'documents');

        $document = Document::query()->sole();

        Storage::disk('documents')->assertExists($document->object_key);

        $this->assertSame(
            hash('sha256', $contents),
            $document->content_sha256,
        );
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame(strlen($contents), $document->size_bytes);
        $this->assertSame('processing', $document->status);
        Queue::assertPushed(
            ProcessUploadedDocument::class,
            fn (ProcessUploadedDocument $job): bool => $job->documentId === $document->getKey(),
        );
        $this->assertStringStartsWith(
            $financialYear->year_code.'/',
            $document->object_key,
        );
    }

    public function test_user_cannot_upload_the_same_file_twice(): void
    {
        Storage::fake('documents');
        Queue::fake();

        [$user, $financialYear, $documentType] = $this->createDocumentContext();
        $contents = "%PDF-1.4\nDuplicate upload test\n%%EOF";

        $this->actingAs($user)->post('/api/documents', [
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->createWithContent('original.pdf', $contents),
        ])->assertCreated();

        $original = Document::query()->sole();

        $this->actingAs($user)->post('/api/documents', [
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->createWithContent('renamed-copy.pdf', $contents),
        ])->assertConflict()
            ->assertJsonPath('code', 'duplicate_document')
            ->assertJsonPath('duplicate_document.id', $original->id)
            ->assertJsonPath('duplicate_document.original_filename', 'original.pdf');

        $this->assertDatabaseCount('documents', 1);
        $this->assertCount(1, Storage::disk('documents')->allFiles());
        $this->assertCount(1, Queue::pushed(ProcessUploadedDocument::class));
    }

    public function test_user_can_upload_without_a_financial_year_for_date_extraction(): void
    {
        Storage::fake('documents');
        Queue::fake();
        [$user, , $documentType] = $this->createDocumentContext();

        $response = $this->actingAs($user)->post('/api/documents', [
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->createWithContent(
                'old-invoice.pdf',
                "%PDF-1.4\nInvoice Date: 03/07/2025\n%%EOF",
            ),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.financial_year_id', null)
            ->assertJsonPath('data.status', 'processing');

        $document = Document::query()->sole();
        $this->assertStringStartsWith('unassigned/', $document->object_key);
        Queue::assertPushed(
            ProcessUploadedDocument::class,
            fn (ProcessUploadedDocument $job): bool => $job->documentId === $document->id,
        );
    }

    public function test_user_can_confirm_date_suggested_financial_year_for_document(): void
    {
        Storage::fake('documents');
        [$user, , $documentType] = $this->createDocumentContext();
        $document = Document::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => null,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'documents',
            'object_key' => 'unassigned/date-suggestion.pdf',
            'content_sha256' => hash('sha256', 'date suggestion'),
            'original_filename' => 'date-suggestion.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'status' => 'awaiting_financial_year',
        ]);
        $document->extractions()->create([
            'provider' => 'local',
            'extraction_version' => 'local-ocr-v1',
            'status' => 'completed',
            'correlation_id' => (string) Str::uuid(),
            'started_at' => now(),
            'completed_at' => now(),
            'output' => [
                'normalized' => [
                    'schema_version' => '1.0',
                    'fields' => [
                        'invoice_date' => [
                            'value' => '2025-07-03',
                            'confidence' => 0.65,
                            'source' => 'local_ocr_candidate',
                        ],
                    ],
                ],
            ],
            'quality_metadata' => [],
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/documents/'.$document->id.'/financial-year', [
                'financial_year_code' => '2025-26',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'review_required');

        $financialYear = FinancialYear::query()
            ->where('year_code', '2025-26')
            ->sole();
        $this->assertSame('2025-26', $financialYear->year_code);
        $this->assertSame($financialYear->id, $document->fresh()->financial_year_id);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_unauthenticated_user_cannot_upload_a_document(): void
    {
        Storage::fake('documents');

        $this->postJson('/api/documents', [])
            ->assertUnauthorized();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_unsupported_file_type_is_rejected(): void
    {
        Storage::fake('documents');

        [$user, $financialYear, $documentType] = $this->createDocumentContext();

        $response = $this->actingAs($user)->post('/api/documents', [
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->create(
                'receipt.txt',
                1,
                'text/plain',
            ),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_user_cannot_upload_to_another_users_financial_year(): void
    {
        Storage::fake('documents');

        [$user, $financialYear, $documentType] = $this->createDocumentContext();

        $otherUser = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'upload-other-owner',
            'email' => 'upload-other-owner@example.com',
            'display_name' => 'Other Owner',
            'status' => 'active',
        ]);

        $response = $this->actingAs($otherUser)->post('/api/documents', [
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->createWithContent(
                'receipt.pdf',
                "%PDF-1.4\nTest\n%%EOF",
            ),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['financial_year_id']);

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_uploaded_file_is_removed_when_database_persistence_fails(): void
    {
        Storage::fake('documents');

        [$user, $financialYear, $documentType] = $this->createDocumentContext();

        $createDocument = Mockery::mock(CreateDocument::class);
        $createDocument
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new \RuntimeException('Simulated database failure'));

        $this->app->instance(CreateDocument::class, $createDocument);

        $file = UploadedFile::fake()->createWithContent(
            'receipt.pdf',
            "%PDF-1.4\nPersistence failure test\n%%EOF",
        );

        try {
            app(UploadDocument::class)->execute(
                user: $user,
                financialYearId: $financialYear->id,
                documentTypeId: $documentType->id,
                file: $file,
            );

            $this->fail('Expected the simulated persistence failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated database failure',
                $exception->getMessage(),
            );
        }

        $this->assertSame([], Storage::disk('documents')->allFiles());
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_existing_unprocessed_document_can_be_queued(): void
    {
        Storage::fake('documents');
        Queue::fake();
        [$user, $financialYear, $documentType] = $this->createDocumentContext();
        $document = Document::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'documents',
            'object_key' => '2026-27/legacy-receipt.pdf',
            'content_sha256' => hash('sha256', 'legacy receipt'),
            'original_filename' => 'legacy-receipt.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->postJson('/api/documents/'.$document->id.'/retry')
            ->assertAccepted()
            ->assertJsonPath('data.status', 'processing');

        Queue::assertPushed(
            ProcessUploadedDocument::class,
            fn (ProcessUploadedDocument $job): bool => $job->documentId === $document->id,
        );
    }

    public function test_user_cannot_retry_another_users_document(): void
    {
        [$owner, $financialYear, $documentType] = $this->createDocumentContext();
        $document = Document::query()->create([
            'user_id' => $owner->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'documents',
            'object_key' => '2026-27/private-receipt.pdf',
            'content_sha256' => hash('sha256', 'private receipt'),
            'original_filename' => 'private-receipt.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->postJson('/api/documents/'.$document->id.'/retry')
            ->assertNotFound();

        $this->assertSame('active', $document->fresh()->status);
    }

    private function createDocumentContext(): array
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'upload-owner-'.uniqid(),
            'email' => 'upload-'.uniqid().'@example.com',
            'display_name' => 'Upload Test User',
            'status' => 'active',
        ]);

        $financialYear = FinancialYear::query()->create([
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $documentType = DocumentType::query()->create([
            'code' => 'UPLOAD_TEST',
            'name' => 'Upload Test Document',
            'description' => 'Document type used by upload API tests',
            'classification_metadata' => [],
            'status' => 'active',
            'sort_order' => 1,
            'version' => '1.0',
        ]);

        return [$user, $financialYear, $documentType];
    }
}
