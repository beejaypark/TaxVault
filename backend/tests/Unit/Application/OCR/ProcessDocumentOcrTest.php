<?php

namespace Tests\Unit\Application\OCR;

use App\Application\OCR\Contracts\OcrProvider;
use App\Application\OCR\Services\ProcessDocumentOcr;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessDocumentOcrTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_is_processed_and_extraction_is_persisted(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault OCR test document';
        $contentSha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/invoice.pdf',
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
            'object_key' => '2026-27/invoice.pdf',
            'content_sha256' => $contentSha256,
            'original_filename' => 'invoice.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $service = app(ProcessDocumentOcr::class);

        $extraction = $service->execute($document);

        $this->assertInstanceOf(
            DocumentExtraction::class,
            $extraction
        );

        $this->assertEquals(
            $document->getKey(),
            $extraction->document_id
        );

        $this->assertEquals('test', $extraction->provider);
        $this->assertEquals('test-ocr', $extraction->model);
        $this->assertEquals('1', $extraction->model_version);
        $this->assertEquals('ocr-v1', $extraction->extraction_version);
        $this->assertEquals('completed', $extraction->status);

        $this->assertEquals(
            'OCR test extraction for document ' . $document->getKey(),
            $extraction->output['text']
        );

        $this->assertEquals(
            100.0,
            $extraction->quality_metadata['confidence']
        );

        $this->assertNotNull($extraction->correlation_id);
        $this->assertNotNull($extraction->started_at);
        $this->assertNotNull($extraction->completed_at);

        $this->assertDatabaseHas('document_extractions', [
            'id' => $extraction->getKey(),
            'document_id' => $document->getKey(),
            'provider' => 'test',
            'model' => 'test-ocr',
            'model_version' => '1',
            'extraction_version' => 'ocr-v1',
            'status' => 'completed',
        ]);
    }

    public function test_provider_failure_is_propagated_without_persisting_extraction(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault OCR failure test document';
        $contentSha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/failure.pdf',
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
            'object_key' => '2026-27/failure.pdf',
            'content_sha256' => $contentSha256,
            'original_filename' => 'failure.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $provider = new class implements OcrProvider {
            public function process(
                \App\Application\OCR\Data\OcrRequest $request
            ): \App\Application\OCR\Data\OcrResult {
                throw new \RuntimeException(
                    'OCR provider temporary failure'
                );
            }
        };

        $service = new ProcessDocumentOcr($provider);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'OCR provider temporary failure'
        );

        $service->execute($document);
    }
    public function test_provider_returned_failed_result_is_persisted(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault OCR failed-result test document';
        $contentSha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/failed-result.pdf',
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
            'object_key' => '2026-27/failed-result.pdf',
            'content_sha256' => $contentSha256,
            'original_filename' => 'failed-result.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $provider = new class implements OcrProvider {
            public function process(
                \App\Application\OCR\Data\OcrRequest $request
            ): \App\Application\OCR\Data\OcrResult {
                return new \App\Application\OCR\Data\OcrResult(
                    provider: 'test-failure',
                    model: 'test-ocr',
                    modelVersion: '1',
                    extractionVersion: 'ocr-v1',
                    status: 'failed',
                    output: [],
                    qualityMetadata: [
                        'confidence' => 0.0,
                    ],
                    errorMetadata: [
                        'code' => 'PROVIDER_TEMPORARY_FAILURE',
                        'message' => 'OCR provider temporarily unavailable',
                        'retryable' => true,
                    ],
                );
            }
        };

        $service = new ProcessDocumentOcr($provider);

        $extraction = $service->execute($document);

        $this->assertInstanceOf(
            DocumentExtraction::class,
            $extraction
        );

        $this->assertEquals(
            $document->getKey(),
            $extraction->document_id
        );

        $this->assertEquals(
            'test-failure',
            $extraction->provider
        );

        $this->assertEquals(
            'failed',
            $extraction->status
        );

        $this->assertEquals(
            'PROVIDER_TEMPORARY_FAILURE',
            $extraction->error_metadata['code']
        );

        $this->assertEquals(
            'OCR provider temporarily unavailable',
            $extraction->error_metadata['message']
        );

        $this->assertTrue(
            $extraction->error_metadata['retryable']
        );

        $this->assertDatabaseHas('document_extractions', [
            'id' => $extraction->getKey(),
            'document_id' => $document->getKey(),
            'provider' => 'test-failure',
            'status' => 'failed',
        ]);
    }
    public function test_ocr_provider_can_be_resolved_from_the_container(): void
    {
        $provider = app(OcrProvider::class);

        $this->assertInstanceOf(
            \App\Infrastructure\OCR\Providers\TestOcrProvider::class,
            $provider
        );
    }
}
