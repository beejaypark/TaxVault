<?php

namespace Tests\Unit\Domain\Evidence\Services;

use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Evidence\Services\TaxRuleEvidenceRecorder;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Tax\Deductibility\Enums\DeductibilityOutcome;
use App\Domain\Tax\Deductibility\ValueObjects\DeductibilityResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TaxRuleEvidenceRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_rule_result_is_recorded_as_evidence(): void
    {
        $user = User::factory()->create();

        $financialYear = new FinancialYear([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);

        $financialYear->save();

        $result = new DeductibilityResult(
            outcome: DeductibilityOutcome::Deductible,
            reason: 'Expenditure is established as wholly business-related and deductible.',
            deductiblePercentage: '100.00',
            financialYearId: $financialYear->id,
            ruleId: 'TAX-004',
            source: 'TaxVault deductibility rules',
        );

        $evidence = (new TaxRuleEvidenceRecorder())->record(
            userId: $user->id,
            result: $result,
            ruleVersion: '2026.1',
            rulesetVersion: 'AUS-TAX-2026.1',
            engineVersion: 'tax-engine-1.0',
        );

        $this->assertInstanceOf(TaxEvidence::class, $evidence);

        $this->assertSame($user->id, $evidence->user_id);
        $this->assertSame($financialYear->id, $evidence->financial_year_id);

        $this->assertSame('tax_treatment', $evidence->evidence_type);
        $this->assertSame('tax_rule', $evidence->source_type);

        $this->assertSame('TAX-004', $evidence->rule_code);
        $this->assertSame('2026.1', $evidence->rule_version);
        $this->assertSame(
            'AUS-TAX-2026.1',
            $evidence->ruleset_version
        );
        $this->assertSame(
            'tax-engine-1.0',
            $evidence->engine_version
        );
        $this->assertSame(
            'TaxVault deductibility rules',
            $evidence->rule_source
        );

        $this->assertSame(
            'TAX-004',
            $evidence->rule_provenance['rule_code']
        );

        $this->assertSame(
            '2026.1',
            $evidence->rule_provenance['rule_version']
        );

        $this->assertSame(
            'AUS-TAX-2026.1',
            $evidence->rule_provenance['ruleset_version']
        );

        $this->assertSame(
            'tax-engine-1.0',
            $evidence->rule_provenance['engine_version']
        );

        $this->assertSame(
            'deductible',
            $evidence->rule_provenance['outcome']
        );

        $this->assertSame(
            '100.00',
            $evidence->rule_provenance['deductible_percentage']
        );

        $this->assertSame(
            'active',
            $evidence->status
        );

        $this->assertSame(
            'pending',
            $evidence->verification_status
        );

        $this->assertSame(
            '100.00',
            (string) $evidence->confidence
        );

        $this->assertDatabaseHas('tax_evidences', [
            'id' => $evidence->id,
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'rule_code' => 'TAX-004',
            'rule_version' => '2026.1',
            'ruleset_version' => 'AUS-TAX-2026.1',
            'engine_version' => 'tax-engine-1.0',
            'rule_source' => 'TaxVault deductibility rules',
        ]);
    }

    public function test_rule_evidence_requires_financial_year(): void
    {
        $user = User::factory()->create();

        $result = new DeductibilityResult(
            outcome: DeductibilityOutcome::Deductible,
            reason: 'Test result.',
            deductiblePercentage: '100.00',
            financialYearId: null,
            ruleId: 'TAX-004',
            source: 'TaxVault deductibility rules',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'A financial year is required to record tax rule evidence.'
        );

        (new TaxRuleEvidenceRecorder())->record(
            userId: $user->id,
            result: $result,
            ruleVersion: '2026.1',
            rulesetVersion: 'AUS-TAX-2026.1',
            engineVersion: 'tax-engine-1.0',
        );
    }

    public function test_rule_evidence_requires_rule_id(): void
    {
        $user = User::factory()->create();

        $financialYear = new FinancialYear([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);

        $financialYear->save();

        $result = new DeductibilityResult(
            outcome: DeductibilityOutcome::ReviewRequired,
            reason: 'Test result.',
            financialYearId: $financialYear->id,
            ruleId: null,
            source: 'TaxVault deductibility rules',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'A rule ID is required to record tax rule evidence.'
        );

        (new TaxRuleEvidenceRecorder())->record(
            userId: $user->id,
            result: $result,
            ruleVersion: '2026.1',
            rulesetVersion: 'AUS-TAX-2026.1',
            engineVersion: 'tax-engine-1.0',
        );
    }

    public function test_rule_evidence_requires_rule_source(): void
    {
        $user = User::factory()->create();

        $financialYear = new FinancialYear([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);

        $financialYear->save();

        $result = new DeductibilityResult(
            outcome: DeductibilityOutcome::Deductible,
            reason: 'Test result.',
            deductiblePercentage: '100.00',
            financialYearId: $financialYear->id,
            ruleId: 'TAX-004',
            source: null,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'A rule source is required to record tax rule evidence.'
        );

        (new TaxRuleEvidenceRecorder())->record(
            userId: $user->id,
            result: $result,
            ruleVersion: '2026.1',
            rulesetVersion: 'AUS-TAX-2026.1',
            engineVersion: 'tax-engine-1.0',
        );
    }
}
