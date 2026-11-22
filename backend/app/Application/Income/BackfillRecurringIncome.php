<?php

namespace App\Application\Income;

use App\Application\Transactions\CreateTransaction;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Income\Models\RecurringIncomeRule;
use App\Domain\Transactions\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class BackfillRecurringIncome
{
    public function __construct(
        private readonly CreateTransaction $createTransaction,
        private readonly CreateIncome $createIncome,
    ) {}

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::today(config('notifications.timezone'));
    }

    public function preview(RecurringIncomeRule $rule): array
    {
        $today = $this->today();
        $year = FinancialYear::query()
            ->where('user_id', $rule->user_id)
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->first();
        $preview = ['year' => $year?->year_code, 'amount' => $rule->amount, 'missing' => [], 'existing' => []];

        if ($year === null || ! $rule->active) {
            return $preview;
        }

        $firstDate = $year->start_date->toDateString();
        $lastDate = $today->toDateString();
        for ($month = CarbonImmutable::parse($firstDate, $today->timezone)->startOfMonth();
            $month->toDateString() <= $lastDate;
            $month = $month->addMonth()
        ) {
            $date = $month->day($rule->day_of_month)->toDateString();
            if ($date >= $firstDate && $this->isDue($rule, $date, $lastDate)) {
                $preview[$this->hasIncome($rule, $date) ? 'existing' : 'missing'][] = $date;
            }
        }

        return $preview;
    }

    public function isDue(RecurringIncomeRule $rule, string $date, string $today): bool
    {
        return $rule->active && $date <= $today && $date >= $rule->start_date->toDateString()
            && ($rule->end_date === null || $date <= $rule->end_date->toDateString());
    }

    public function createMissing(RecurringIncomeRule $rule, string $date): bool
    {
        if ($this->hasIncome($rule, $date)) {
            return false;
        }

        $transaction = $this->createTransaction->execute(
            user: $rule->user,
            transactionDate: $date,
            direction: 'income',
            amount: $rule->amount,
            description: $rule->description,
            sourceSystem: 'recurring_income',
            externalTransactionId: 'recurring-income:'.$rule->getKey().':'.$date,
        );
        $transaction->update(['ato_category' => $rule->ato_category]);
        $this->createIncome->execute(
            user: $rule->user,
            transaction: $transaction,
            amount: $transaction->amount,
            sourceSystem: 'recurring_income',
        );

        return true;
    }

    private function hasIncome(RecurringIncomeRule $rule, string $date): bool
    {
        return Transaction::withoutGlobalScopes()
            ->where('user_id', $rule->user_id)
            ->where(fn ($query) => $query
                ->where('external_transaction_id', 'recurring-income:'.$rule->getKey().':'.$date)
                ->orWhere(fn ($income) => $income->where('direction', 'income')->whereDate('transaction_date', $date)))
            ->exists();
    }
}
