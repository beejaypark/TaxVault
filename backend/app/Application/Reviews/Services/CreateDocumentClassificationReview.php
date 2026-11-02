<?php

namespace App\Application\Reviews\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Reviews\Models\Review;

final class CreateDocumentClassificationReview
{
    public function execute(
        Document $document,
        DocumentExtraction $extraction,
    ): Review {
        return Review::query()->firstOrCreate(
            [
                'document_extraction_id' => $extraction->getKey(),
                'review_type' => 'classification',
                'status' => 'pending',
            ],
            [
                'reviewer_id' => $document->user_id,
                'document_id' => $document->getKey(),
                'transaction_id' => null,
                'decision' => null,
                'reviewer_notes' => 'Confirm the extracted details and classify this document before recording any transaction.',
                'supersedes_review_id' => null,
                'completed_at' => null,
            ],
        );
    }
}
