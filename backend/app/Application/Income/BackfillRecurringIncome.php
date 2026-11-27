<?php

namespace App\Application\Income;

use App\Application\Transactions\CreateTransaction;
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
        $year = $rule->financialYear;
        $preview = ['year' => $year?->year_code, 'amount' => $rule->amount, 'missing' => [], 'existing' => []];

        if ($year === null || ! $rule->active) {
            return $preview;
        }

        $firstDate = max($year->start_date->toDateString(), $rule->start_date->toDateString());
        $lastDate = min($year->end_date->toDateString(), $today->toDateString());
        if ($rule->end_date !== null) {
            $lastDate = min($lastDate, $rule->end_date->toDateString());
        }
        if ($firstDate > $lastDate) {
            return $preview;
        }

        for ($month = CarbonImmutable::parse($firstDate, $today->timezone)->startOfMonth();
            $month->toDateString() <= $lastDate;
            $month = $month->addMonth()
        ) {
            $date = $month->day($rule->day_of_month)->toDateString();
            if ($date >= $firstDate && $this->isDue($rule, $date, $today->toDateString())) {
                $preview[$this->hasIncome($rule, $date) ? 'existing' : 'missing'][] = $date;
            }
        }

        return $preview;
    }

    public function isDue(RecurringIncomeRule $rule, string $date, string $today): bool
    {
        $year = $rule->financialYear;

        return $year !== null && $rule->active
            && $date >= $year->start_date->toDateString()
            && $date <= $year->end_date->toDateString()
            && $date <= $today && $date >= $rule->start_date->toDateString()
            && ($rule->end_date === null || $date <= $rule->end_date->toDateString());
    }

    public function createMissing(RecurringIncomeRule $rule, string $date): bool
    {
        if (! $this->isDue($rule, $date, $this->today()->toDateString())) {
            return false;
        }

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
