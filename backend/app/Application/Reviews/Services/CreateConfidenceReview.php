<?php

namespace App\Application\Reviews\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Reviews\Models\Review;
use App\Application\OCR\Confidence\ConfidenceEvaluationResult;

final class CreateConfidenceReview
{
    public function execute(
        Document $document,
        DocumentExtraction $extraction,
        ConfidenceEvaluationResult $evaluation,
    ): Review {
        $existingReview = Review::query()
            ->where(
                'document_extraction_id',
                $extraction->getKey()
            )
            ->where('review_type', 'confidence')
            ->where('status', 'pending')
            ->first();

        if ($existingReview !== null) {
            return $existingReview;
        }

        return Review::query()->create([
            'reviewer_id' => $document->user_id,
            'document_id' => $document->getKey(),
            'document_extraction_id' => $extraction->getKey(),
            'transaction_id' => null,
            'review_type' => 'confidence',
            'status' => 'pending',
            'decision' => null,
            'reviewer_notes' => $this->buildReviewerNotes($evaluation),
            'supersedes_review_id' => null,
            'completed_at' => null,
        ]);
    }

    private function buildReviewerNotes(
        ConfidenceEvaluationResult $evaluation,
    ): string {
        return 'OCR confidence review required for fields: '
            . implode(', ', $evaluation->reviewFields);
    }
}
