<?php

namespace App\Domain\Tax\Deductibility\Services;

use App\Domain\Tax\Deductibility\Enums\DeductibilityOutcome;
use App\Domain\Tax\Deductibility\ValueObjects\DeductibilityFacts;
use App\Domain\Tax\Deductibility\ValueObjects\DeductibilityResult;

final class DeductibilityEvaluator
{
    private const RULE_ID = 'TAX-004';
    private const SOURCE = 'TaxVault deductibility rules';

    public function evaluate(
        DeductibilityFacts $facts,
        ?string $financialYearId = null,
    ): DeductibilityResult {
        if (!$facts->businessPurpose && !$facts->privatePurpose) {
            return new DeductibilityResult(
                outcome: DeductibilityOutcome::ReviewRequired,
                reason: 'Purpose of expenditure is not sufficiently established.',
                financialYearId: $financialYearId,
                ruleId: self::RULE_ID,
                source: self::SOURCE,
            );
        }

        if ($facts->privatePurpose) {
            return new DeductibilityResult(
                outcome: DeductibilityOutcome::NonDeductible,
                reason: 'Private expenditure is not deductible.',
                deductiblePercentage: '0.00',
                financialYearId: $financialYearId,
                ruleId: self::RULE_ID,
                source: self::SOURCE,
            );
        }

        if ($facts->capitalAsset) {
            if (!$facts->immediateDeductionEligible) {
                return new DeductibilityResult(
                    outcome: DeductibilityOutcome::Capital,
                    reason: 'Capital expenditure is subject to capital treatment rather than immediate deduction.',
                    financialYearId: $financialYearId,
                    ruleId: self::RULE_ID,
                    source: self::SOURCE,
                );
            }
        }

        if ($facts->businessUsePercentage !== null) {
            $percentage = number_format(
                (float) $facts->businessUsePercentage,
                2,
                '.',
                ''
            );

            if ((float) $percentage < 100) {
                return new DeductibilityResult(
                    outcome: DeductibilityOutcome::Partial,
                    reason: 'Deduction is limited to the established business-use proportion.',
                    deductiblePercentage: $percentage,
                    financialYearId: $financialYearId,
                    ruleId: self::RULE_ID,
                    source: self::SOURCE,
                );
            }
        }

        return new DeductibilityResult(
            outcome: DeductibilityOutcome::Deductible,
            reason: 'Expenditure is established as wholly business-related and deductible.',
            deductiblePercentage: '100.00',
            financialYearId: $financialYearId,
            ruleId: self::RULE_ID,
            source: self::SOURCE,
        );
    }
}
