<?php

namespace App\Application\OCR\Confidence;

final readonly class ConfidenceEvaluationResult
{
    /**
     * @param array<string, array{
     *     confidence: float,
     *     threshold: float,
     *     critical: bool,
     *     status: string
     * }> $fields
     * @param list<string> $reviewFields
     */
    public function __construct(
        public string $status,
        public array $fields,
        public array $reviewFields,
    ) {
    }

    public function requiresReview(): bool
    {
        return $this->status === 'review';
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'fields' => $this->fields,
            'review_fields' => $this->reviewFields,
        ];
    }
}
