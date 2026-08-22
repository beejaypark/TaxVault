<?php

namespace Tests\Feature\Income;

use App\Application\Income\CreateIncome;
use App\Application\Transactions\CreateTransaction;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateIncomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_inherits_transaction_financial_year(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $transaction = app(CreateTransaction::class)->execute(
            user: $user,
            transactionDate: '2026-04-10',
            direction: 'income',
            amount: '1500.00',
        );

        $income = app(CreateIncome::class)->execute(
            user: $user,
            transaction: $transaction,
            amount: '1500.00',
        );

        $this->assertSame($fy->getKey(), $transaction->financial_year_id);
        $this->assertSame($transaction->financial_year_id, $income->financial_year_id);
        $this->assertTrue($income->financialYear->is($fy));
    }

    public function test_income_on_july_1_uses_next_financial_year(): void
    {
        $user = User::factory()->create();

        FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $nextFy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $transaction = app(CreateTransaction::class)->execute(
            user: $user,
            transactionDate: '2026-07-01',
            direction: 'income',
            amount: '2000.00',
        );

        $income = app(CreateIncome::class)->execute(
            user: $user,
            transaction: $transaction,
            amount: '2000.00',
        );

        $this->assertSame($nextFy->getKey(), $income->financial_year_id);
    }

    public function test_income_cannot_use_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        FinancialYear::create([
            'user_id' => $owner->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $transaction = app(CreateTransaction::class)->execute(
            user: $owner,
            transactionDate: '2026-03-15',
            direction: 'income',
            amount: '1000.00',
        );

        $this->expectException(\LogicException::class);

        app(CreateIncome::class)->execute(
            user: $otherUser,
            transaction: $transaction,
            amount: '1000.00',
        );
    }
}
