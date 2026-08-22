<?php

namespace Tests\Unit\Domain\Tax\Deductibility;

use App\Domain\Tax\Deductibility\Enums\DeductibilityOutcome;
use App\Domain\Tax\Deductibility\Services\DeductibilityEvaluator;
use App\Domain\Tax\Deductibility\ValueObjects\DeductibilityFacts;
use PHPUnit\Framework\TestCase;

class DeductibilityEvaluatorTest extends TestCase
{
    private DeductibilityEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new DeductibilityEvaluator();
    }

    public function test_wholly_business_expenditure_is_deductible(): void
    {
        $result = $this->evaluator->evaluate(
            new DeductibilityFacts(
                businessPurpose: true,
            ),
            'fy-2026',
        );

        $this->assertSame(
            DeductibilityOutcome::Deductible,
            $result->outcome
        );
        $this->assertSame('100.00', $result->deductiblePercentage);
        $this->assertSame('fy-2026', $result->financialYearId);
        $this->assertSame('TAX-004', $result->ruleId);
        $this->assertNotEmpty($result->source);
    }

    public function test_private_expenditure_is_not_deductible(): void
    {
        $result = $this->evaluator->evaluate(
            new DeductibilityFacts(
                businessPurpose: false,
                privatePurpose: true,
            ),
        );

        $this->assertSame(
            DeductibilityOutcome::NonDeductible,
            $result->outcome
        );
        $this->assertSame('0.00', $result->deductiblePercentage);
    }

    public function test_mixed_business_use_is_partially_deductible(): void
    {
        $result = $this->evaluator->evaluate(
            new DeductibilityFacts(
                businessPurpose: true,
                businessUsePercentage: '60.00',
            ),
        );

        $this->assertSame(
            DeductibilityOutcome::Partial,
            $result->outcome
        );
        $this->assertSame('60.00', $result->deductiblePercentage);
    }

    public function test_capital_expenditure_is_capital_when_not_immediately_deductible(): void
    {
        $result = $this->evaluator->evaluate(
            new DeductibilityFacts(
                businessPurpose: true,
                capitalAsset: true,
            ),
        );

        $this->assertSame(
            DeductibilityOutcome::Capital,
            $result->outcome
        );
        $this->assertNull($result->deductiblePercentage);
    }

    public function test_missing_purpose_requires_review(): void
    {
        $result = $this->evaluator->evaluate(
            new DeductibilityFacts(
                businessPurpose: false,
                privatePurpose: false,
            ),
        );

        $this->assertSame(
            DeductibilityOutcome::ReviewRequired,
            $result->outcome
        );
        $this->assertNull($result->deductiblePercentage);
    }

    public function test_invalid_business_use_percentage_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DeductibilityFacts(
            businessPurpose: true,
            businessUsePercentage: '101.00',
        );
    }

    public function test_result_contains_explanation_and_provenance(): void
    {
        $result = $this->evaluator->evaluate(
            new DeductibilityFacts(
                businessPurpose: true,
            ),
            'fy-2026',
        );

        $this->assertNotEmpty($result->reason);
        $this->assertSame('TAX-004', $result->ruleId);
        $this->assertSame(
            'TaxVault deductibility rules',
            $result->source
        );
    }
}
