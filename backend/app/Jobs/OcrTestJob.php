<?php

namespace App\Jobs;

use App\Application\OCR\Services\ProcessDocumentOcr;
use App\Domain\Documents\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OcrTestJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $documentId,
    ) {
    }

    public function handle(
        ProcessDocumentOcr $processDocumentOcr,
    ): void {
        $document = Document::query()
            ->findOrFail($this->documentId);

        $processDocumentOcr->execute($document);
    }
}
