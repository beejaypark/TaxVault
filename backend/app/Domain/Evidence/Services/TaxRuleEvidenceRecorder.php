<?php

namespace App\Domain\Evidence\Services;

use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Tax\Deductibility\ValueObjects\DeductibilityResult;
use InvalidArgumentException;

final class TaxRuleEvidenceRecorder
{
    private const EVIDENCE_TYPE = 'tax_treatment';
    private const SOURCE_TYPE = 'tax_rule';
    private const STATUS = 'active';
    private const VERIFICATION_STATUS = 'pending';
    private const CONFIDENCE = '100.00';

    public function record(
        string $userId,
        DeductibilityResult $result,
        string $ruleVersion,
        string $rulesetVersion,
        string $engineVersion,
    ): TaxEvidence {
        if ($result->financialYearId === null) {
            throw new InvalidArgumentException(
                'A financial year is required to record tax rule evidence.'
            );
        }

        if ($result->ruleId === null) {
            throw new InvalidArgumentException(
                'A rule ID is required to record tax rule evidence.'
            );
        }

        if ($result->source === null) {
            throw new InvalidArgumentException(
                'A rule source is required to record tax rule evidence.'
            );
        }

        return TaxEvidence::create([
            'user_id' => $userId,
            'financial_year_id' => $result->financialYearId,

            'evidence_type' => self::EVIDENCE_TYPE,
            'source_type' => self::SOURCE_TYPE,
            'source_id' => null,

            'rule_code' => $result->ruleId,
            'rule_version' => $ruleVersion,
            'ruleset_version' => $rulesetVersion,
            'engine_version' => $engineVersion,
            'rule_source' => $result->source,

            'rule_provenance' => [
                'rule_code' => $result->ruleId,
                'rule_version' => $ruleVersion,
                'ruleset_version' => $rulesetVersion,
                'engine_version' => $engineVersion,
                'rule_source' => $result->source,
                'outcome' => $result->outcome->value,
                'reason' => $result->reason,
                'deductible_percentage' => $result->deductiblePercentage,
                'financial_year_id' => $result->financialYearId,
            ],

            'status' => self::STATUS,
            'verification_status' => self::VERIFICATION_STATUS,
            'confidence' => self::CONFIDENCE,
        ]);
    }
}
