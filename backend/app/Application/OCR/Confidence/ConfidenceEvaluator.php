<?php

namespace App\Application\OCR\Confidence;

use App\Application\OCR\Data\NormalizedExtraction;

final class ConfidenceEvaluator
{
    public function __construct(
        private readonly ConfidencePolicy $policy = new ConfidencePolicy(),
    ) {
    }

    public function evaluate(
        NormalizedExtraction $extraction
    ): ConfidenceEvaluationResult {
        $fields = [];
        $reviewFields = [];

        foreach ($extraction->fields as $fieldPath => $field) {
            $confidence = (float) $field['confidence'];
            $threshold = $this->policy->thresholdFor($fieldPath);
            $critical = in_array(
                $fieldPath,
                $this->policy->criticalFields,
                true
            );

            $status = $confidence >= $threshold
                ? 'pass'
                : 'review';

            $fields[$fieldPath] = [
                'confidence' => $confidence,
                'threshold' => $threshold,
                'critical' => $critical,
                'status' => $status,
            ];

            if ($status === 'review') {
                $reviewFields[] = $fieldPath;
            }
        }

        return new ConfidenceEvaluationResult(
            status: $reviewFields === [] ? 'pass' : 'review',
            fields: $fields,
            reviewFields: $reviewFields,
        );
    }
}
