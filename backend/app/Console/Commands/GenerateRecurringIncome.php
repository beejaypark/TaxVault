<?php

namespace App\Console\Commands;

use App\Application\Income\CreateIncome;
use App\Application\Transactions\CreateTransaction;
use App\Domain\Income\Models\RecurringIncomeRule;
use App\Domain\Transactions\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringIncome extends Command
{
    protected $signature = 'income:generate-recurring';
    protected $description = 'Create due recurring income transactions without duplicates';

    public function handle(CreateTransaction $createTransaction, CreateIncome $createIncome): int
    {
        $today = Carbon::today();
        $created = 0;
        RecurringIncomeRule::query()->where('active', true)->whereDate('start_date', '<=', $today)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))->each(function (RecurringIncomeRule $rule) use ($today, $createTransaction, $createIncome, &$created): void {
            $date = $today->copy()->day(min($rule->day_of_month, $today->daysInMonth));
            if ($date->isFuture() || $date->lt($rule->start_date) || ($rule->end_date && $date->gt($rule->end_date))) return;
            $externalId = 'recurring-income:' . $rule->id . ':' . $date->toDateString();
            if (Transaction::withoutGlobalScopes()->where('user_id', $rule->user_id)->where('external_transaction_id', $externalId)->exists()) return;
            DB::transaction(function () use ($rule, $date, $externalId, $createTransaction, $createIncome, &$created): void {
                $transaction = $createTransaction->execute(user: $rule->user, transactionDate: $date->toDateString(), direction: 'income', amount: (string) $rule->amount, description: $rule->description, sourceSystem: 'recurring_income', externalTransactionId: $externalId);
                $transaction->update(['ato_category' => $rule->ato_category]);
                $createIncome->execute(user: $rule->user, transaction: $transaction, amount: $transaction->amount, sourceSystem: 'recurring_income');
                $created++;
            });
        });
        $this->info("Created {$created} recurring income transaction(s).");
        return self::SUCCESS;
    }
}
