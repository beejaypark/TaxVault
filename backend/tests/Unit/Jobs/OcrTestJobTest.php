<?php

namespace Tests\Unit\Jobs;

use App\Application\OCR\Services\ProcessDocumentOcr;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Jobs\OcrTestJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrTestJobTest extends TestCase
{
    use RefreshDatabase;

    private function createDocument(): Document
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault OCR job service test document';
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

        return Document::query()->create([
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
    }

    public function test_job_processes_document_through_application_service(): void
    {
        $document = $this->createDocument();

        $job = new OcrTestJob(
            documentId: $document->getKey(),
        );

        $job->handle(
            app(ProcessDocumentOcr::class),
        );

        $this->assertDatabaseHas('document_extractions', [
            'document_id' => $document->getKey(),
            'provider' => 'test',
            'model' => 'test-ocr',
            'model_version' => '1',
            'extraction_version' => 'ocr-v1',
            'status' => 'completed',
        ]);

        $extraction = DocumentExtraction::query()
            ->where('document_id', $document->getKey())
            ->firstOrFail();

        $this->assertSame(
            'OCR test extraction for document ' . $document->getKey(),
            $extraction->output['text'],
        );
    }

    public function test_job_can_be_dispatched_with_document_identity(): void
    {
        $document = $this->createDocument();

        Queue::fake();

        OcrTestJob::dispatch(
            $document->getKey(),
        );

        Queue::assertPushed(
            OcrTestJob::class,
            function (OcrTestJob $job) use ($document): bool {
                return $job->documentId === $document->getKey();
            },
        );
    }
}
