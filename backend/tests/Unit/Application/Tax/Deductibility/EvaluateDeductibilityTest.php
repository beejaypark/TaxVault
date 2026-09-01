<?php

namespace Tests\Unit\Application\Tax\Deductibility;

use App\Application\Tax\Deductibility\EvaluateDeductibility;
use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Evidence\Services\TaxRuleEvidenceRecorder;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Tax\Deductibility\Enums\DeductibilityOutcome;
use App\Domain\Tax\Deductibility\Services\DeductibilityEvaluator;
use App\Domain\Tax\Deductibility\ValueObjects\DeductibilityFacts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluateDeductibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_deductibility_result_is_recorded_as_tax_evidence(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $service = new EvaluateDeductibility(
            new DeductibilityEvaluator(),
            new TaxRuleEvidenceRecorder(),
        );

        $result = $service->execute(
            userId: $user->id,
            facts: new DeductibilityFacts(
                businessPurpose: true,
            ),
            financialYearId: $financialYear->id,
            ruleVersion: '2026.1',
            rulesetVersion: 'AUS-TAX-2026.1',
            engineVersion: 'tax-engine-1.0',
        );

        $this->assertSame(
            DeductibilityOutcome::Deductible,
            $result['result']->outcome,
        );

        $this->assertSame(
            '100.00',
            $result['result']->deductiblePercentage,
        );

        $this->assertInstanceOf(
            TaxEvidence::class,
            $result['evidence'],
        );

        $this->assertSame(
            $financialYear->id,
            $result['evidence']->financial_year_id,
        );

        $this->assertSame(
            'TAX-004',
            $result['evidence']->rule_code,
        );

        $this->assertSame(
            '2026.1',
            $result['evidence']->rule_version,
        );

        $this->assertSame(
            'AUS-TAX-2026.1',
            $result['evidence']->ruleset_version,
        );

        $this->assertSame(
            'tax-engine-1.0',
            $result['evidence']->engine_version,
        );

        $this->assertSame(
            'TaxVault deductibility rules',
            $result['evidence']->rule_source,
        );

        $this->assertSame(
            'TAX-004',
            $result['evidence']->rule_provenance['rule_code'],
        );

        $this->assertSame(
            '2026.1',
            $result['evidence']->rule_provenance['rule_version'],
        );

        $this->assertSame(
            'AUS-TAX-2026.1',
            $result['evidence']->rule_provenance['ruleset_version'],
        );

        $this->assertSame(
            'tax-engine-1.0',
            $result['evidence']->rule_provenance['engine_version'],
        );

        $this->assertSame(
            'deductible',
            $result['evidence']->rule_provenance['outcome'],
        );

        $this->assertSame(
            '100.00',
            $result['evidence']->rule_provenance['deductible_percentage'],
        );
    }

    public function test_private_expenditure_is_recorded_as_non_deductible_evidence(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $service = new EvaluateDeductibility(
            new DeductibilityEvaluator(),
            new TaxRuleEvidenceRecorder(),
        );

        $result = $service->execute(
            userId: $user->id,
            facts: new DeductibilityFacts(
                businessPurpose: false,
                privatePurpose: true,
            ),
            financialYearId: $financialYear->id,
            ruleVersion: '2026.1',
            rulesetVersion: 'AUS-TAX-2026.1',
            engineVersion: 'tax-engine-1.0',
        );

        $this->assertSame(
            DeductibilityOutcome::NonDeductible,
            $result['result']->outcome,
        );

        $this->assertSame(
            'non_deductible',
            $result['evidence']->rule_provenance['outcome'],
        );

        $this->assertSame(
            '0.00',
            $result['evidence']->rule_provenance['deductible_percentage'],
        );
    }

    public function test_partial_deductibility_is_recorded_with_percentage(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $service = new EvaluateDeductibility(
            new DeductibilityEvaluator(),
            new TaxRuleEvidenceRecorder(),
        );

        $result = $service->execute(
            userId: $user->id,
            facts: new DeductibilityFacts(
                businessPurpose: true,
                businessUsePercentage: '60.00',
            ),
            financialYearId: $financialYear->id,
            ruleVersion: '2026.1',
            rulesetVersion: 'AUS-TAX-2026.1',
            engineVersion: 'tax-engine-1.0',
        );

        $this->assertSame(
            DeductibilityOutcome::Partial,
            $result['result']->outcome,
        );

        $this->assertSame(
            '60.00',
            $result['result']->deductiblePercentage,
        );

        $this->assertSame(
            'partial',
            $result['evidence']->rule_provenance['outcome'],
        );

        $this->assertSame(
            '60.00',
            $result['evidence']->rule_provenance['deductible_percentage'],
        );
    }

    private function createFinancialYear(User $user): FinancialYear
    {
        $financialYear = new FinancialYear([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);

        $financialYear->save();

        return $financialYear;
    }
}

