<?php

namespace App\Domain\Tax\Classification\Services;

use App\Domain\Tax\Classification\ValueObjects\ClassificationResult;

final class TaxClassificationService
{
    /**
     * @param array<int, array{
     *     taxonomy_version:string,
     *     facts:array<string, scalar|array|null>,
     *     category_id:string,
     *     subcategory_id:?string,
     *     confidence:string
     * }> $rules
     */
    public function __construct(
        private readonly array $rules = [],
    ) {}

    /**
     * @param array<string, mixed> $extractedFacts
     */
    public function classify(
        array $extractedFacts,
        string $taxonomyVersion,
    ): ClassificationResult {
        foreach ($this->rules as $rule) {
            if ($rule['taxonomy_version'] !== $taxonomyVersion) {
                continue;
            }

            if (! $this->matchesFacts(
                $extractedFacts,
                $rule['facts'],
            )) {
                continue;
            }

            $confidence = $this->normaliseConfidence(
                $rule['confidence'],
            );

            return new ClassificationResult(
                taxonomyVersion: $taxonomyVersion,
                categoryId: $rule['category_id'],
                subcategoryId: $rule['subcategory_id'],
                confidence: $confidence,
                source: 'rule',
                provenance: [
                    'rule_taxonomy_version' => $taxonomyVersion,
                    'matched_facts' => $rule['facts'],
                ],
                requiresReview: bccomp($confidence, '0.80', 2) < 0,
            );
        }

        return new ClassificationResult(
            taxonomyVersion: $taxonomyVersion,
            categoryId: null,
            subcategoryId: null,
            confidence: '0.00',
            source: 'unclassified',
            provenance: [
                'rule_taxonomy_version' => $taxonomyVersion,
                'reason' => 'no_matching_rule',
            ],
            requiresReview: true,
        );
    }

    /**
     * @param array<string, mixed> $facts
     * @param array<string, scalar|array|null> $requirements
     */
    private function matchesFacts(
        array $facts,
        array $requirements,
    ): bool {
        foreach ($requirements as $key => $expected) {
            if (! array_key_exists($key, $facts)) {
                return false;
            }

            if ($facts[$key] !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function normaliseConfidence(string $confidence): string
    {
        return number_format(
            max(0.00, min(1.00, (float) $confidence)),
            2,
            '.',
            '',
        );
    }
}
