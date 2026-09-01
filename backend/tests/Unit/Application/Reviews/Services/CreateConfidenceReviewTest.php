<?php

namespace Tests\Unit\Application\Reviews\Services;

use App\Application\OCR\Confidence\ConfidenceEvaluationResult;
use App\Application\Reviews\Services\CreateConfidenceReview;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Reviews\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateConfidenceReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_all_low_confidence_fields_in_reviewer_notes(): void
    {
        $user = User::factory()->create();

        $financialYear = FinancialYear::query()->create([
            'user_id' => $user->getKey(),
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $documentType = DocumentType::query()->create([
            'code' => 'invoice',
            'name' => 'Invoice',
            'description' => 'Invoice document',
            'metadata' => [],
            'status' => 'active',
        ]);

        $document = Document::query()->create([
            'user_id' => $user->getKey(),
            'financial_year_id' => $financialYear->getKey(),
            'document_type_id' => $documentType->getKey(),
            'storage_disk' => 'documents',
            'object_key' => '2026-27/multiple-review-fields.pdf',
            'content_sha256' => hash(
                'sha256',
                'multiple-review-fields'
            ),
            'original_filename' => 'multiple-review-fields.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 23,
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $extraction = DocumentExtraction::query()->create([
            'document_id' => $document->getKey(),
            'provider' => 'test-confidence',
            'model' => 'test-ocr',
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

        $evaluation = new ConfidenceEvaluationResult(
            status: 'review',
            fields: [
                'supplier' => [
                    'confidence' => 72.0,
                    'threshold' => 90.0,
                    'critical' => false,
                    'status' => 'review',
                ],
                'amount' => [
                    'confidence' => 65.0,
                    'threshold' => 90.0,
                    'critical' => false,
                    'status' => 'review',
                ],
                'invoice_date' => [
                    'confidence' => 95.0,
                    'threshold' => 90.0,
                    'critical' => false,
                    'status' => 'pass',
                ],
            ],
            reviewFields: [
                'supplier',
                'amount',
            ],
        );

        $service = new CreateConfidenceReview();

        $review = $service->execute(
            $document,
            $extraction,
            $evaluation,
        );

        $this->assertSame(
            'OCR confidence review required for fields: supplier, amount',
            $review->reviewer_notes
        );

        $this->assertSame(
            'confidence',
            $review->review_type
        );

        $this->assertSame(
            'pending',
            $review->status
        );

        $this->assertSame(
            $document->getKey(),
            $review->document_id
        );

        $this->assertSame(
            $extraction->getKey(),
            $review->document_extraction_id
        );
    }
    public function test_it_does_not_create_duplicate_pending_confidence_reviews(): void
    {
        $user = User::factory()->create();

        $financialYear = FinancialYear::query()->create([
            'user_id' => $user->getKey(),
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $documentType = DocumentType::query()->create([
            'code' => 'invoice',
            'name' => 'Invoice',
            'description' => 'Invoice document',
            'metadata' => [],
            'status' => 'active',
        ]);

        $document = Document::query()->create([
            'user_id' => $user->getKey(),
            'financial_year_id' => $financialYear->getKey(),
            'document_type_id' => $documentType->getKey(),
            'storage_disk' => 'documents',
            'object_key' => '2026-27/idempotency.pdf',
            'content_sha256' => hash(
                'sha256',
                'idempotency'
            ),
            'original_filename' => 'idempotency.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 11,
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $extraction = DocumentExtraction::query()->create([
            'document_id' => $document->getKey(),
            'provider' => 'test-confidence',
            'model' => 'test-ocr',
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

        $evaluation = new ConfidenceEvaluationResult(
            status: 'review',
            fields: [
                'amount' => [
                    'confidence' => 65.0,
                    'threshold' => 90.0,
                    'critical' => false,
                    'status' => 'review',
                ],
            ],
            reviewFields: [
                'amount',
            ],
        );

        $service = new CreateConfidenceReview();

        $firstReview = $service->execute(
            $document,
            $extraction,
            $evaluation,
        );

        $secondReview = $service->execute(
            $document,
            $extraction,
            $evaluation,
        );

        $this->assertSame(
            $firstReview->getKey(),
            $secondReview->getKey()
        );

        $this->assertSame(
            1,
            Review::query()
                ->where(
                    'document_extraction_id',
                    $extraction->getKey()
                )
                ->where('review_type', 'confidence')
                ->where('status', 'pending')
                ->count()
        );
    }
    public function test_it_creates_a_pending_confidence_review(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 confidence review test';
        $contentSha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/review-test.pdf',
            $content
        );

        $user = User::factory()->create();

        $financialYear = FinancialYear::query()->create([
            'user_id' => $user->getKey(),
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $documentType = DocumentType::query()->create([
            'code' => 'invoice',
            'name' => 'Invoice',
            'description' => 'Invoice document',
            'metadata' => [],
            'status' => 'active',
        ]);

        $document = Document::query()->create([
            'user_id' => $user->getKey(),
            'financial_year_id' => $financialYear->getKey(),
            'document_type_id' => $documentType->getKey(),
            'storage_disk' => 'documents',
            'object_key' => '2026-27/review-test.pdf',
            'content_sha256' => $contentSha256,
            'original_filename' => 'review-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $extraction = DocumentExtraction::query()->create([
            'document_id' => $document->getKey(),
            'provider' => 'test-confidence',
            'model' => 'test-ocr',
            'model_version' => '1',
            'extraction_version' => 'ocr-v1',
            'status' => 'completed',
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'started_at' => now(),
            'completed_at' => now(),
            'output' => [
                'normalized' => [
                    'schema_version' => 'ocr-extraction-v1',
                    'fields' => [],
                ],
            ],
            'quality_metadata' => [],
            'error_metadata' => null,
        ]);

        $evaluation = new ConfidenceEvaluationResult(
            status: 'review',
            fields: [
                'amount' => [
                    'confidence' => 72.0,
                    'threshold' => 90.0,
                    'critical' => false,
                    'status' => 'review',
                ],
            ],
            reviewFields: ['amount'],
        );

        $review = (new CreateConfidenceReview())->execute(
            $document,
            $extraction,
            $evaluation,
        );

        $this->assertInstanceOf(Review::class, $review);

        $this->assertSame(
            $user->getKey(),
            $review->reviewer_id
        );

        $this->assertSame(
            $document->getKey(),
            $review->document_id
        );

        $this->assertSame(
            $extraction->getKey(),
            $review->document_extraction_id
        );

        $this->assertNull($review->transaction_id);

        $this->assertSame(
            'confidence',
            $review->review_type
        );

        $this->assertSame(
            'pending',
            $review->status
        );

        $this->assertNull($review->decision);

        $this->assertSame(
            'OCR confidence review required for fields: amount',
            $review->reviewer_notes
        );

        $this->assertNull($review->supersedes_review_id);
        $this->assertNull($review->completed_at);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->getKey(),
            'reviewer_id' => $user->getKey(),
            'document_id' => $document->getKey(),
            'document_extraction_id' => $extraction->getKey(),
            'review_type' => 'confidence',
            'status' => 'pending',
        ]);
    }
}

