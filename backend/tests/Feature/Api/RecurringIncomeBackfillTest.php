<?php

namespace Tests\Feature\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Income\Models\RecurringIncomeRule;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringIncomeBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_previews_only_due_payments_in_the_current_year_and_creates_only_confirmed_payments(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 00:10', 'Australia/Sydney'));
        $user = User::factory()->create();
        $year = FinancialYear::query()->create([
            'user_id' => $user->getKey(),
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
        $rule = RecurringIncomeRule::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'Employer',
            'description' => 'Monthly income',
            'start_date' => '2025-07-01',
            'day_of_month' => 14,
            'amount' => '1000.00',
        ]);
        Transaction::query()->create([
            'user_id' => $user->getKey(),
            'financial_year_id' => $year->getKey(),
            'transaction_date' => '2026-08-14',
            'direction' => 'income',
            'description' => 'Existing income',
            'currency' => 'AUD',
            'amount' => '1000.00',
        ]);
        $endpoint = "/api/recurring-income/{$rule->getKey()}/missing";
        $this->actingAs($user)->getJson($endpoint)->assertOk()
            ->assertJsonPath('data.missing', ['2026-07-14', '2026-09-14'])
            ->assertJsonPath('data.existing', ['2026-08-14']);
        $this->assertDatabaseCount('transactions', 1);

        $confirmation = ['year' => '2026-27', 'amount' => '1000.00', 'dates' => ['2026-07-14']];
        $this->postJson($endpoint, array_replace($confirmation, ['dates' => ['2026-06-14']]))->assertConflict();
        $this->postJson($endpoint, $confirmation)->assertOk()->assertJsonPath('data.created', 1);
        $this->postJson($endpoint, $confirmation)->assertOk()->assertJsonPath('data.skipped', 1);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', ['transaction_date' => '2026-07-14', 'source_system' => 'recurring_income']);
    }
}
