<?php

namespace Tests\Feature\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_record_income_and_expenses(): void
    {
        $user = User::factory()->create();
        $financialYear = FinancialYear::query()->create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);
        $this->actingAs($user);

        $this->postJson('/api/transactions', [
            'transaction_date' => '2025-08-15',
            'direction' => 'income',
            'amount' => 1200.50,
            'description' => 'Interest income',
        ])->assertCreated()
            ->assertJsonPath('data.direction', 'income')
            ->assertJsonPath('data.financial_year_id', $financialYear->id);

        $this->postJson('/api/transactions', [
            'transaction_date' => '2025-08-16',
            'direction' => 'expense',
            'amount' => 85.25,
            'description' => 'Tax record expense',
        ])->assertCreated()
            ->assertJsonPath('data.direction', 'expense');

        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseCount('income', 1);
        $this->assertDatabaseCount('expenses', 1);
        $this->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
