<?php

namespace App\Application\OCR\Confidence;

final readonly class ConfidencePolicy
{
    public function __construct(
        public float $defaultThreshold = 90.0,
        public float $criticalThreshold = 90.0,
        public array $criticalFields = [],
    ) {
    }

    public function thresholdFor(string $fieldPath): float
    {
        return in_array($fieldPath, $this->criticalFields, true)
            ? $this->criticalThreshold
            : $this->defaultThreshold;
    }
}
