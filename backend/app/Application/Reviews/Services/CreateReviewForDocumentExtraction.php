<?php

namespace App\Application\Reviews\Services;

use App\Application\OCR\Confidence\ConfidenceEvaluator;
use App\Application\OCR\Data\NormalizedExtraction;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Reviews\Models\Review;

final class CreateReviewForDocumentExtraction
{
    public function __construct(
        private readonly ConfidenceEvaluator $confidenceEvaluator = new ConfidenceEvaluator,
        private readonly CreateConfidenceReview $createConfidenceReview = new CreateConfidenceReview,
        private readonly CreateDocumentClassificationReview $createClassificationReview = new CreateDocumentClassificationReview,
    ) {}

    public function execute(
        Document $document,
        DocumentExtraction $extraction,
        NormalizedExtraction $normalizedExtraction,
    ): Review {
        $evaluation = $this->confidenceEvaluator->evaluate($normalizedExtraction);

        if ($evaluation->requiresReview()) {
            return $this->createConfidenceReview->execute(
                $document,
                $extraction,
                $evaluation,
            );
        }

        return $this->createClassificationReview->execute(
            $document,
            $extraction,
        );
    }
}
