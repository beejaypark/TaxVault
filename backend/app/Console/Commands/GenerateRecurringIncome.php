<?php

namespace App\Console\Commands;

use App\Application\Income\BackfillRecurringIncome;
use App\Domain\Income\Models\RecurringIncomeRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringIncome extends Command
{
    protected $signature = 'income:generate-recurring';
    protected $description = 'Create due recurring income transactions without duplicates';

    public function handle(BackfillRecurringIncome $income): int
    {
        $today = $income->today();
        $created = 0;
        RecurringIncomeRule::query()->where('active', true)
            ->whereDate('start_date', '<=', $today->toDateString())
            ->each(function (RecurringIncomeRule $rule) use ($today, $income, &$created): void {
                DB::transaction(function () use ($rule, $today, $income, &$created): void {
                    $locked = RecurringIncomeRule::query()->lockForUpdate()->find($rule->getKey());
                    if ($locked === null) return;

                    $date = $today->day($locked->day_of_month)->toDateString();
                    if ($income->isDue($locked, $date, $today->toDateString())) {
                        $created += (int) $income->createMissing($locked, $date);
                    }
                });
            });
        $this->info("Created {$created} recurring income transaction(s).");
        return self::SUCCESS;
    }
}
