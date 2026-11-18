<?php

namespace Tests\Feature\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_year_is_not_reported_as_ready(): void
    {
        [$user, $year] = $this->userAndYear();

        $response = $this->actingAs($user)->getJson("/api/financial-years/{$year->id}/tax-readiness");

        $response->assertOk()
            ->assertJsonPath('data.score', null)
            ->assertJsonPath('data.status', 'not_started')
            ->assertJsonPath('data.calculation_version', 'v3')
            ->assertJsonPath('data.components.documents.applicable', false)
            ->assertJsonPath('data.components.transactions.applicable', false);
    }

    public function test_transaction_gaps_are_exposed_with_denominators(): void
    {
        [$user, $year] = $this->userAndYear();

        Transaction::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $year->id,
            'transaction_date' => '2026-01-10',
            'direction' => 'expense',
            'amount' => '120.00',
            'description' => 'Uncategorised expense',
            'source_system' => 'manual',
        ]);

        $response = $this->actingAs($user)->getJson("/api/financial-years/{$year->id}/tax-readiness");

        $response->assertOk()
            ->assertJsonPath('data.status', 'needs_attention')
            ->assertJsonPath('data.components.transactions.completed', 0)
            ->assertJsonPath('data.components.transactions.total', 1)
            ->assertJsonPath('data.components.evidence.completed', 0)
            ->assertJsonPath('data.components.evidence.total', 0)
            ->assertJsonPath('data.components.required_data.percentage', 100)
            ->assertJsonPath('data.issues.0.code', 'uncategorised_transactions');
    }

    public function test_missing_transaction_fields_are_reported_as_incomplete_required_data(): void
    {
        [$user, $year] = $this->userAndYear();

        Transaction::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $year->id,
            'transaction_date' => '2026-01-10',
            'direction' => 'expense',
            'amount' => '120.00',
            'description' => '',
            'source_system' => 'manual',
        ]);

        $response = $this->actingAs($user)->getJson("/api/financial-years/{$year->id}/tax-readiness");

        $response->assertOk()
            ->assertJsonPath('data.components.required_data.completed', 0)
            ->assertJsonPath('data.components.required_data.percentage', 0)
            ->assertJsonFragment(['code' => 'incomplete_required_data', 'count' => 1]);
    }

    public function test_categorised_manual_income_without_evidence_is_reported(): void
    {
        [$user, $year] = $this->userAndYear();

        Transaction::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $year->id,
            'transaction_date' => '2026-01-10',
            'direction' => 'income',
            'amount' => '120.00',
            'description' => 'Manual income',
            'ato_category' => 'other_income',
            'source_system' => 'manual',
        ]);

        $response = $this->actingAs($user)->getJson("/api/financial-years/{$year->id}/tax-readiness");

        $response->assertOk()
            ->assertJsonPath('data.components.evidence.completed', 0)
            ->assertJsonPath('data.components.evidence.total', 1)
            ->assertJsonFragment(['code' => 'missing_evidence', 'count' => 1]);
    }

    /** @return array{0: User, 1: FinancialYear} */
    private function userAndYear(): array
    {
        $user = User::factory()->create();
        $year = FinancialYear::query()->create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        return [$user, $year];
    }
}
