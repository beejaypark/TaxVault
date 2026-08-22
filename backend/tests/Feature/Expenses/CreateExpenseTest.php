<?php

namespace Tests\Feature\Expenses;

use App\Application\Expenses\CreateExpense;
use App\Application\Transactions\CreateTransaction;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_inherits_transaction_financial_year(): void
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
            transactionDate: '2026-03-15',
            direction: 'expense',
            amount: '125.50',
        );

        $expense = app(CreateExpense::class)->execute(
            user: $user,
            transaction: $transaction,
            amount: '125.50',
        );

        $this->assertSame($fy->getKey(), $transaction->financial_year_id);
        $this->assertSame($transaction->financial_year_id, $expense->financial_year_id);
        $this->assertTrue($expense->financialYear->is($fy));
    }

    public function test_expense_on_june_30_keeps_current_financial_year(): void
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
            transactionDate: '2026-06-30',
            direction: 'expense',
            amount: '80.00',
        );

        $expense = app(CreateExpense::class)->execute(
            user: $user,
            transaction: $transaction,
            amount: '80.00',
        );

        $this->assertSame($fy->getKey(), $expense->financial_year_id);
    }

    public function test_expense_cannot_use_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $owner->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $transaction = app(CreateTransaction::class)->execute(
            user: $owner,
            transactionDate: '2026-03-15',
            direction: 'expense',
            amount: '50.00',
        );

        $this->expectException(\LogicException::class);

        app(CreateExpense::class)->execute(
            user: $otherUser,
            transaction: $transaction,
            amount: '50.00',
        );
    }
}
