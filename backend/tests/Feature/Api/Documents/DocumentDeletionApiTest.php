<?php

namespace Tests\Feature\Api\Documents;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\Services\FinancialYearExportService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentDeletionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_a_document_and_its_reviews_while_preserving_other_linked_records(): void
    {
        Storage::fake('documents');
        [$user, $financialYear, $documentType] = $this->createDocumentContext();

        $document = Document::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'documents',
            'object_key' => '2026-27/receipt.pdf',
            'content_sha256' => hash('sha256', 'receipt'),
            'original_filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'status' => 'review_required',
        ]);
        Storage::disk('documents')->put($document->object_key, 'receipt');

        $extractionId = (string) Str::uuid();
        DB::table('document_extractions')->insert([
            'id' => $extractionId,
            'document_id' => $document->id,
            'provider' => 'test',
            'extraction_version' => 'test-v1',
            'status' => 'completed',
            'output' => json_encode([]),
            'quality_metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transactionId = (string) Str::uuid();
        DB::table('transactions')->insert([
            'id' => $transactionId,
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_id' => $document->id,
            'transaction_date' => '2026-09-10',
            'direction' => 'expense',
            'amount' => 12.50,
            'currency' => 'AUD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reviewId = (string) Str::uuid();
        DB::table('reviews')->insert([
            'id' => $reviewId,
            'reviewer_id' => $user->id,
            'document_id' => $document->id,
            'review_type' => 'classification',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evidenceId = (string) Str::uuid();
        DB::table('tax_evidences')->insert([
            'id' => $evidenceId,
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_id' => $document->id,
            'evidence_type' => 'document',
            'source_type' => 'document',
            'source_id' => $document->id,
            'status' => 'active',
            'verification_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linkId = (string) Str::uuid();
        DB::table('financial_record_documents')->insert([
            'id' => $linkId,
            'document_id' => $document->id,
            'transaction_id' => $transactionId,
            'relation_type' => 'supporting_document',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/documents/'.$document->id)
            ->assertOk()
            ->assertJsonPath('message', 'Document permanently deleted.');

        $this->actingAs($user)
            ->getJson('/api/reviews?financial_year_id='.$financialYear->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $export = app(FinancialYearExportService::class)->export(
            $user,
            $financialYear->id,
        );

        $this->assertSame(
            $document->id,
            $export->payload['source_references']['document_extractions'][0]['source_document_id'],
        );
        $this->assertSame(
            $document->id,
            $export->payload['evidence_chain']['financial_record_documents'][0]['source_document_id'],
        );
        $this->assertSame([], $export->payload['verification_history']['reviews']);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('documents')->assertMissing($document->object_key);
        $this->assertDatabaseHas('document_extractions', [
            'id' => $extractionId,
            'document_id' => null,
            'source_document_id' => $document->id,
            'source_financial_year_id' => $financialYear->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'document_id' => null,
            'source_document_id' => $document->id,
        ]);
        $this->assertDatabaseMissing('reviews', ['id' => $reviewId]);
        $this->assertDatabaseHas('tax_evidences', [
            'id' => $evidenceId,
            'document_id' => null,
            'source_id' => $document->id,
        ]);
        $this->assertDatabaseHas('financial_record_documents', [
            'id' => $linkId,
            'document_id' => null,
            'source_document_id' => $document->id,
            'transaction_id' => $transactionId,
        ]);
    }

    public function test_owner_can_delete_an_unassigned_document(): void
    {
        Storage::fake('documents');
        [$user, , $documentType] = $this->createDocumentContext();
        $document = Document::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => null,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'documents',
            'object_key' => 'unassigned/old-receipt.pdf',
            'content_sha256' => hash('sha256', 'old receipt'),
            'original_filename' => 'old-receipt.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'status' => 'awaiting_financial_year',
        ]);
        Storage::disk('documents')->put($document->object_key, 'old receipt');

        $this->actingAs($user)
            ->deleteJson('/api/documents/'.$document->id)
            ->assertOk();

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('documents')->assertMissing($document->object_key);
    }

    public function test_user_cannot_delete_another_users_document(): void
    {
        Storage::fake('documents');
        [$owner, , $documentType] = $this->createDocumentContext();
        $document = Document::query()->create([
            'user_id' => $owner->id,
            'financial_year_id' => null,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'documents',
            'object_key' => 'unassigned/private.pdf',
            'content_sha256' => hash('sha256', 'private'),
            'original_filename' => 'private.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'status' => 'awaiting_financial_year',
        ]);
        Storage::disk('documents')->put($document->object_key, 'private');
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->deleteJson('/api/documents/'.$document->id)
            ->assertNotFound();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        Storage::disk('documents')->assertExists($document->object_key);
    }

    public function test_document_cannot_be_deleted_while_processing(): void
    {
        [$user, , $documentType] = $this->createDocumentContext();
        $document = Document::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => null,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'documents',
            'object_key' => 'unassigned/processing.pdf',
            'content_sha256' => hash('sha256', 'processing'),
            'original_filename' => 'processing.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'status' => 'processing',
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/documents/'.$document->id)
            ->assertConflict()
            ->assertJsonPath('message', 'This document cannot be deleted while text extraction is in progress. Try again when processing finishes.');

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    private function createDocumentContext(): array
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'delete-owner-'.uniqid(),
            'email' => 'delete-'.uniqid().'@example.com',
            'display_name' => 'Document Delete Test User',
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
            'code' => 'DELETE_TEST',
            'name' => 'Delete Test Document',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        return [$user, $financialYear, $documentType];
    }
}
