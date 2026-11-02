<?php

namespace App\Jobs;

use App\Application\OCR\Services\ProcessDocumentOcr;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ProcessUploadedDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $documentId,
    ) {}

    public function handle(ProcessDocumentOcr $processDocumentOcr): void
    {
        $document = Document::query()->findOrFail($this->documentId);
        $processDocumentOcr->execute($document);
    }

    public function failed(Throwable $exception): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $document->update(['status' => 'processing_failed']);
        DocumentExtraction::query()->create([
            'document_id' => $document->getKey(),
            'provider' => (string) config('ocr.provider', 'local'),
            'extraction_version' => 'local-ocr-v1',
            'status' => 'failed',
            'correlation_id' => (string) Str::uuid(),
            'started_at' => now(),
            'completed_at' => now(),
            'output' => [],
            'quality_metadata' => [],
            'error_metadata' => [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ],
        ]);
    }
}
