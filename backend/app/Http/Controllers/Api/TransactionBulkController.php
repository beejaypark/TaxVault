<?php

namespace App\Http\Controllers\Api;

use App\Application\Expenses\CreateExpense;
use App\Application\Income\CreateIncome;
use App\Application\Transactions\CreateTransaction;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\Property;
use App\Domain\Transactions\AtoCategory;
use App\Domain\Transactions\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionBulkController extends Controller
{
    public function categorise(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['uuid'],
            'ato_category' => ['nullable', 'string', Rule::in(AtoCategory::codes())],
            'property_id' => ['nullable', 'uuid'],
        ]);

        $category = $data['ato_category'] ?? null;
        $propertyId = $data['property_id'] ?? null;

        if ($category !== null && AtoCategory::isRental($category)) {
            $this->assertOwnedProperty($userId, $propertyId);
        } else {
            $propertyId = null;
        }

        $transactions = Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('id', $data['ids'])
            ->get();

        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($transactions, $category, $propertyId, &$updated, &$skipped): void {
            foreach ($transactions as $transaction) {
                $definition = $category !== null ? AtoCategory::all()[$category] : null;

                if ($definition !== null && $definition['direction'] !== $transaction->direction) {
                    $skipped++;

                    continue;
                }

                $transaction->update(['ato_category' => $category, 'property_id' => $propertyId]);
                $updated++;
            }
        });

        return response()->json(['data' => [
            'updated' => $updated,
            'skipped' => $skipped + (count($data['ids']) - $transactions->count()),
        ]]);
    }

    public function import(
        Request $request,
        CreateTransaction $createTransaction,
        CreateIncome $createIncome,
        CreateExpense $createExpense,
    ): JsonResponse {
        $user = $request->user();
        $userId = $user->getKey();

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:1000'],
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.description' => ['required', 'string', 'max:1000'],
            'rows.*.amount' => ['required', 'numeric', 'not_in:0'],
            'rows.*.ato_category' => ['nullable', 'string', Rule::in(AtoCategory::codes())],
            'rows.*.property_id' => ['nullable', 'uuid'],
        ]);

        $years = FinancialYear::query()->where('user_id', $userId)->get(['start_date', 'end_date']);
        $ownedProperties = Property::query()->where('user_id', $userId)->pluck('id')->all();

        $keys = [];
        $seen = [];
        foreach ($data['rows'] as $index => $row) {
            $base = sha1($row['date'].'|'.number_format(abs((float) $row['amount']), 2, '.', '').'|'.($row['amount'] < 0 ? 'd' : 'c').'|'.mb_strtolower(trim($row['description'])));
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $keys[$index] = 'csv:'.$base.':'.$seen[$base];
        }

        $existing = Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('external_transaction_id', array_values($keys))
            ->pluck('external_transaction_id')
            ->flip();

        $imported = 0;
        $duplicates = 0;
        $outsideYears = 0;

        DB::transaction(function () use (
            $data, $years, $ownedProperties, $keys, $existing, $user, $createTransaction, $createIncome, $createExpense,
            &$imported, &$duplicates, &$outsideYears,
        ): void {
            foreach ($data['rows'] as $index => $row) {
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

                $transaction = $createTransaction->execute(
                    user: $user,
                    transactionDate: $row['date'],
                    direction: $direction,
                    amount: $amount,
                    description: $row['description'],
                    sourceSystem: 'csv_import',
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

                $record = $direction === 'income' ? $createIncome : $createExpense;
                $record->execute(
                    user: $user,
                    transaction: $transaction,
                    amount: $transaction->amount,
                    sourceSystem: 'csv_import',
                );

                $imported++;
            }
        });

        return response()->json(['data' => [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'outside_financial_years' => $outsideYears,
        ]]);
    }

    private function assertOwnedProperty(string $userId, ?string $propertyId): void
    {
        $owned = $propertyId !== null && Property::query()
            ->whereKey($propertyId)
            ->where('user_id', $userId)
            ->exists();

        if (! $owned) {
            throw ValidationException::withMessages(['property_id' => 'Choose one of your rental properties.']);
        }
    }
}
