<?php

namespace App\Http\Controllers\Api;

use App\Application\Transactions\ImportTransactions;
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
        ImportTransactions $importTransactions,
    ): JsonResponse {
        $user = $request->user();
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:1000'],
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.description' => ['required', 'string', 'max:1000'],
            'rows.*.amount' => ['required', 'numeric', 'not_in:0'],
            'rows.*.ato_category' => ['nullable', 'string', Rule::in(AtoCategory::codes())],
            'rows.*.property_id' => ['nullable', 'uuid'],
        ]);

        $keys = [];
        $seen = [];
        foreach ($data['rows'] as $index => $row) {
            $base = sha1($row['date'].'|'.number_format(abs((float) $row['amount']), 2, '.', '').'|'.($row['amount'] < 0 ? 'd' : 'c').'|'.mb_strtolower(trim($row['description'])));
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $keys[$index] = 'csv:'.$base.':'.$seen[$base];
        }

        return response()->json(['data' => $importTransactions->execute($user, $data['rows'], $keys, 'csv_import')]);
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
