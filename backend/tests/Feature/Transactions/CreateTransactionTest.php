<?php

namespace Tests\Feature\Transactions;

use App\Application\Transactions\CreateTransaction;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_financial_year_is_derived_from_transaction_date(): void
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

        $this->assertTrue($transaction->financialYear->is($fy));
        $this->assertSame($fy->getKey(), $transaction->financial_year_id);
    }

    public function test_transaction_on_june_30_belongs_to_current_financial_year(): void
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
            amount: '100.00',
        );

        $this->assertSame($fy->getKey(), $transaction->financial_year_id);
    }

    public function test_transaction_on_july_1_belongs_to_next_financial_year(): void
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
            amount: '500.00',
        );

        $this->assertSame($nextFy->getKey(), $transaction->financial_year_id);
    }

    public function test_transaction_cannot_be_created_when_no_financial_year_matches(): void
    {
        $user = User::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        app(CreateTransaction::class)->execute(
            user: $user,
            transactionDate: '2026-03-15',
            direction: 'expense',
            amount: '50.00',
        );
    }
}
