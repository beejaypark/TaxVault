<?php

namespace Tests\Unit\Application\OCR;

use App\Application\OCR\Services\ProcessDocumentOcr;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Jobs\OcrTestJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrTestJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_processes_document_ocr(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault OCR job test document';
        $contentSha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/job-invoice.pdf',
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
            'object_key' => '2026-27/job-invoice.pdf',
            'content_sha256' => $contentSha256,
            'original_filename' => 'job-invoice.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $job = new OcrTestJob(
            documentId: $document->getKey(),
        );

        $job->handle(
            app(ProcessDocumentOcr::class),
        );

        $extraction = DocumentExtraction::query()
            ->where('document_id', $document->getKey())
            ->first();

        $this->assertNotNull($extraction);
        $this->assertSame('test', $extraction->provider);
        $this->assertSame('test-ocr', $extraction->model);
        $this->assertSame('completed', $extraction->status);
        $this->assertSame(
            'OCR test extraction for document ' . $document->getKey(),
            $extraction->output['text'],
        );
    }

    public function test_job_uses_document_id_as_payload(): void
    {
        $job = new OcrTestJob(
            documentId: '00000000-0000-0000-0000-000000000001',
        );

        $this->assertSame(
            '00000000-0000-0000-0000-000000000001',
            $job->documentId,
        );
    }
}
