<?php

namespace Tests\Unit\Application\OCR;

use App\Application\OCR\Contracts\OcrProvider;
use App\Application\OCR\Data\OcrResult;
use App\Application\OCR\Services\ProcessDocumentOcr;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reviews\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessDocumentOcrConfidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confidence_evaluation_is_persisted_for_completed_extraction(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault confidence test document';
        $contentSha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/confidence.pdf',
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
            'object_key' => '2026-27/confidence.pdf',
            'content_sha256' => $contentSha256,
            'original_filename' => 'confidence.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $provider = new class implements OcrProvider {
            public function process(
                \App\Application\OCR\Data\OcrRequest $request
            ): OcrResult {
                return new OcrResult(
                    provider: 'test-confidence',
                    model: 'test-ocr',
                    modelVersion: '1',
                    extractionVersion: 'ocr-v1',
                    status: 'completed',
                    output: [
                        'text' => 'Example invoice',
                        'normalized' => [
                            'schema_version' => 'ocr-extraction-v1',
                            'fields' => [
                                'supplier' => [
                                    'value' => 'Example Pty Ltd',
                                    'confidence' => 98.0,
                                    'source' => 'ocr',
                                ],
                                'amount' => [
                                    'value' => 110.00,
                                    'confidence' => 72.0,
                                    'source' => 'ocr',
                                ],
                            ],
                        ],
                    ],
                    qualityMetadata: [
                        'confidence' => 85.0,
                    ],
                );
            }
        };

        $service = new ProcessDocumentOcr($provider);

        $extraction = $service->execute($document);

        $evaluation = $extraction->quality_metadata[
            'confidence_evaluation'
        ];

        $this->assertSame('review', $evaluation['status']);

        $this->assertSame(
            ['amount'],
            $evaluation['review_fields']
        );

        $this->assertSame(
            'pass',
            $evaluation['fields']['supplier']['status']
        );

        $this->assertSame(
            'review',
            $evaluation['fields']['amount']['status']
        );

        $this->assertTrue(
            $extraction->quality_metadata['review_required']
        );

        $this->assertDatabaseHas('reviews', [
            'reviewer_id' => $user->getKey(),
            'document_id' => $document->getKey(),
            'document_extraction_id' => $extraction->getKey(),
            'transaction_id' => null,
            'review_type' => 'confidence',
            'status' => 'pending',
        ]);

        $review = Review::query()
            ->where('document_extraction_id', $extraction->getKey())
            ->firstOrFail();

        $this->assertSame(
            'OCR confidence review required for fields: amount',
            $review->reviewer_notes
        );
    }

    public function test_high_confidence_completed_extraction_does_not_create_review(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 High confidence test document';
        $contentSha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/high-confidence.pdf',
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
            'object_key' => '2026-27/high-confidence.pdf',
            'content_sha256' => $contentSha256,
            'original_filename' => 'high-confidence.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $provider = new class implements OcrProvider {
            public function process(
                \App\Application\OCR\Data\OcrRequest $request
            ): OcrResult {
                return new OcrResult(
                    provider: 'test-high-confidence',
                    model: 'test-ocr',
                    modelVersion: '1',
                    extractionVersion: 'ocr-v1',
                    status: 'completed',
                    output: [
                        'text' => 'High confidence invoice',
                        'normalized' => [
                            'schema_version' => 'ocr-extraction-v1',
                            'fields' => [
                                'supplier' => [
                                    'value' => 'Example Pty Ltd',
                                    'confidence' => 98.0,
                                    'source' => 'ocr',
                                ],
                                'amount' => [
                                    'value' => 110.00,
                                    'confidence' => 95.0,
                                    'source' => 'ocr',
                                ],
                            ],
                        ],
                    ],
                    qualityMetadata: [
                        'confidence' => 96.0,
                    ],
                );
            }
        };

        $service = new ProcessDocumentOcr($provider);

        $extraction = $service->execute($document);

        $evaluation = $extraction->quality_metadata[
            'confidence_evaluation'
        ];

        $this->assertSame(
            'pass',
            $evaluation['status']
        );

        $this->assertSame(
            [],
            $evaluation['review_fields']
        );

        $this->assertFalse(
            $extraction->quality_metadata['review_required']
        );

        $this->assertDatabaseMissing('reviews', [
            'document_extraction_id' => $extraction->getKey(),
            'review_type' => 'confidence',
        ]);
    }
}
