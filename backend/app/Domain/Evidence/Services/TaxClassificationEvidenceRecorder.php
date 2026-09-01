<?php

namespace App\Domain\Evidence\Services;

use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Tax\Classification\ValueObjects\ClassificationResult;
use InvalidArgumentException;

final class TaxClassificationEvidenceRecorder
{
    private const EVIDENCE_TYPE = 'classification';

    private const SOURCE_TYPE = 'classification';

    private const STATUS = 'active';

    private const VERIFICATION_STATUS = 'pending';

    /**
     * Record a tax classification result as immutable tax evidence.
     *
     * @throws InvalidArgumentException
     */
    public function record(
        string $userId,
        string $financialYearId,
        ClassificationResult $result,
        string $rulesetVersion,
        string $engineVersion,
    ): TaxEvidence {
        if ($userId === '') {
            throw new InvalidArgumentException(
                'A user ID is required to record classification evidence.'
            );
        }

        if ($financialYearId === '') {
            throw new InvalidArgumentException(
                'A financial year ID is required to record classification evidence.'
            );
        }

        if ($result->categoryId === null) {
            throw new InvalidArgumentException(
                'A tax category is required to record classification evidence.'
            );
        }

        if ($rulesetVersion === '') {
            throw new InvalidArgumentException(
                'A ruleset version is required to record classification evidence.'
            );
        }

        if ($engineVersion === '') {
            throw new InvalidArgumentException(
                'An engine version is required to record classification evidence.'
            );
        }

        $classificationReason = $this->resolveClassificationReason($result);

        $ruleCode = $this->resolveRuleCode($result);

        return TaxEvidence::create([
            'user_id' => $userId,

            /*
             * Classification evidence must always belong to an
             * explicit financial year.
             */
            'financial_year_id' => $financialYearId,

            'evidence_type' => self::EVIDENCE_TYPE,
            'source_type' => self::SOURCE_TYPE,
            'source_id' => null,

            'tax_category_id' => $result->categoryId,
            'tax_subcategory_id' => $result->subcategoryId,

            'classification_reason' => $classificationReason,

            'rule_code' => $ruleCode,
            'rule_version' => $result->taxonomyVersion,
            'ruleset_version' => $rulesetVersion,
            'engine_version' => $engineVersion,
            'rule_source' => $result->source,

            'rule_provenance' => [
                'taxonomy_version' => $result->taxonomyVersion,
                'category_id' => $result->categoryId,
                'subcategory_id' => $result->subcategoryId,
                'confidence' => $result->confidence,
                'source' => $result->source,
                'requires_review' => $result->requiresReview,

                'ruleset_version' => $rulesetVersion,
                'engine_version' => $engineVersion,

                'provenance' => $result->provenance,
            ],

            'status' => self::STATUS,
            'verification_status' => self::VERIFICATION_STATUS,

            /*
             * ClassificationResult confidence is represented as
             * 0.00 - 1.00.
             *
             * TaxEvidence stores confidence as 0.00 - 100.00.
             */
            'confidence' => $this->confidencePercentage(
                $result->confidence
            ),
        ]);
    }

    /**
     * Resolve a stable rule identifier from classification provenance.
     */
    private function resolveRuleCode(
        ClassificationResult $result,
    ): ?string {
        $provenance = $result->provenance;

        if (
            isset($provenance['rule_code'])
            && is_string($provenance['rule_code'])
            && $provenance['rule_code'] !== ''
        ) {
            return $provenance['rule_code'];
        }

        return null;
    }

    /**
     * Resolve a human-readable classification reason.
     */
    private function resolveClassificationReason(
        ClassificationResult $result,
    ): string {
        $provenance = $result->provenance;

        if (
            isset($provenance['reason'])
            && is_string($provenance['reason'])
            && $provenance['reason'] !== ''
        ) {
            return $provenance['reason'];
        }

        if (
            isset($provenance['classification_reason'])
            && is_string($provenance['classification_reason'])
            && $provenance['classification_reason'] !== ''
        ) {
            return $provenance['classification_reason'];
        }

        if ($result->source === 'rule') {
            return 'Classification was determined by the configured tax classification rule.';
        }

        if ($result->source === 'unclassified') {
            return 'Classification could not be determined by the configured tax classification rules.';
        }

        return 'Classification was determined by the tax classification engine.';
    }

    /**
     * Convert classification confidence from 0.00 - 1.00
     * into evidence confidence 0.00 - 100.00.
     */
    private function confidencePercentage(
        string $confidence,
    ): string {
        $value = (float) $confidence;

        $value = max(0.00, min(1.00, $value));

        return number_format(
            $value * 100,
            2,
            '.',
            '',
        );
    }
}
