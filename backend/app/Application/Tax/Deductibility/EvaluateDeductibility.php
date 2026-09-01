<?php

namespace App\Application\Tax\Deductibility;

use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Evidence\Services\TaxRuleEvidenceRecorder;
use App\Domain\Tax\Deductibility\Services\DeductibilityEvaluator;
use App\Domain\Tax\Deductibility\ValueObjects\DeductibilityFacts;
use App\Domain\Tax\Deductibility\ValueObjects\DeductibilityResult;

final class EvaluateDeductibility
{
    public function __construct(
        private readonly DeductibilityEvaluator $evaluator,
        private readonly TaxRuleEvidenceRecorder $evidenceRecorder,
    ) {}

    /**
     * Evaluate expenditure and persist the resulting tax-rule evidence.
     *
     * The evaluator remains a pure domain rule service.
     * Evidence persistence is handled by the application orchestration layer.
     *
     * @return array{
     *     result: DeductibilityResult,
     *     evidence: TaxEvidence
     * }
     */
    public function execute(
        string $userId,
        DeductibilityFacts $facts,
        string $financialYearId,
        string $ruleVersion,
        string $rulesetVersion,
        string $engineVersion,
    ): array {
        $result = $this->evaluator->evaluate(
            facts: $facts,
            financialYearId: $financialYearId,
        );

        $evidence = $this->evidenceRecorder->record(
            userId: $userId,
            result: $result,
            ruleVersion: $ruleVersion,
            rulesetVersion: $rulesetVersion,
            engineVersion: $engineVersion,
        );

        return [
            'result' => $result,
            'evidence' => $evidence,
        ];
    }
}
