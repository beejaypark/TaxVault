<?php

namespace App\Application\Transactions;

use App\Application\Expenses\CreateExpense;
use App\Application\Income\CreateIncome;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\Property;
use App\Domain\Transactions\AtoCategory;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportTransactions
{
    public function __construct(
        private readonly CreateTransaction $createTransaction,
        private readonly CreateIncome $createIncome,
        private readonly CreateExpense $createExpense,
    ) {}

    /**
     * @param  list<array{date: string, description: string, amount: numeric, ato_category?: ?string, property_id?: ?string}>  $rows
     * @param  array<int, string>  $keys  external transaction id per row index
     * @return array{imported: int, duplicates: int, outside_financial_years: int}
     */
    public function execute(User $user, array $rows, array $keys, string $sourceSystem): array
    {
        $userId = $user->getKey();
        $years = FinancialYear::query()->where('user_id', $userId)->get(['start_date', 'end_date']);
        $ownedProperties = Property::query()->where('user_id', $userId)->pluck('id')->all();

        $existing = Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('external_transaction_id', array_values($keys))
            ->pluck('external_transaction_id')
            ->flip();

        $imported = 0;
        $duplicates = 0;
        $outsideYears = 0;

        DB::transaction(function () use ($rows, $years, $ownedProperties, $keys, $existing, $user, $sourceSystem, &$imported, &$duplicates, &$outsideYears): void {
            foreach ($rows as $index => $row) {
                if ($existing->has($keys[$index])) {
                    $duplicates++;

                    continue;
                }

                $covered = $years->contains(fn ($y) => $row['date'] >= $y->start_date->toDateString() && $row['date'] <= $y->end_date->toDateString());

                if (! $covered) {
                    $outsideYears++;

                    continue;
                }

                $direction = $row['amount'] < 0 ? 'expense' : 'income';
                $amount = number_format(abs((float) $row['amount']), 2, '.', '');

                $transaction = $this->createTransaction->execute(
                    user: $user,
                    transactionDate: $row['date'],
                    direction: $direction,
                    amount: $amount,
                    description: $row['description'],
                    sourceSystem: $sourceSystem,
                    externalTransactionId: $keys[$index],
                );

                $category = $row['ato_category'] ?? null;
                $definition = $category ? AtoCategory::all()[$category] : null;
                $propertyId = $row['property_id'] ?? null;

                if ($definition === null || $definition['direction'] !== $direction) {
                    $category = null;
                    $propertyId = null;
                } elseif (AtoCategory::isRental($category)) {
                    $propertyId = in_array($propertyId, $ownedProperties, true) ? $propertyId : null;
                    $category = $propertyId ? $category : null;
                } else {
                    $propertyId = null;
                }

                $transaction->update(['ato_category' => $category, 'property_id' => $propertyId]);

                $record = $direction === 'income' ? $this->createIncome : $this->createExpense;
                $record->execute(
                    user: $user,
                    transaction: $transaction,
                    amount: $transaction->amount,
                    sourceSystem: $sourceSystem,
                );

                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'outside_financial_years' => $outsideYears,
        ];
    }
}
