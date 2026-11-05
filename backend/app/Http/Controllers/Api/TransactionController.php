<?php

namespace App\Http\Controllers\Api;

use App\Application\Expenses\CreateExpense;
use App\Application\Income\CreateIncome;
use App\Application\Transactions\CreateTransaction;
use App\Domain\FinancialYears\Services\FinancialYearResolver;
use App\Domain\Transactions\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $transactions = Transaction::query()
            ->where('user_id', $request->user()->getKey())
            ->with('document')
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $transactions,
        ]);
    }

    public function store(
        StoreTransactionRequest $request,
        CreateTransaction $createTransaction,
        CreateIncome $createIncome,
        CreateExpense $createExpense,
    ): JsonResponse {
        $transaction = DB::transaction(function () use (
            $request,
            $createTransaction,
            $createIncome,
            $createExpense,
        ): Transaction {
            $transaction = $createTransaction->execute(
                user: $request->user(),
                transactionDate: $request->string('transaction_date')->toString(),
                direction: $request->string('direction')->toString(),
                amount: $request->string('amount')->toString(),
                currency: $request->input('currency', 'AUD'),
                description: $request->string('description')->toString(),
                sourceSystem: 'manual',
            );

            $transaction->update([
                'ato_category' => $request->input('ato_category'),
                'property_id' => $request->input('property_id'),
            ]);

            if ($transaction->direction === 'income') {
                $createIncome->execute(
                    user: $request->user(),
                    transaction: $transaction,
                    amount: $transaction->amount,
                    sourceSystem: 'manual',
                );
            } else {
                $createExpense->execute(
                    user: $request->user(),
                    transaction: $transaction,
                    amount: $transaction->amount,
                    sourceSystem: 'manual',
                );
            }

            return $transaction;
        });

        return response()->json([
            'data' => $transaction->load('document'),
        ], 201);
    }

    public function update(
        StoreTransactionRequest $request,
        string $id,
        FinancialYearResolver $resolver,
        CreateIncome $createIncome,
        CreateExpense $createExpense,
    ): JsonResponse {
        $transaction = $this->findOwned($request, $id);

        try {
            $financialYear = $resolver->resolve(
                $request->user()->getKey(),
                $request->string('transaction_date')->toString(),
            );
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'transaction_date' => 'No financial year covers this date. Add the financial year first.',
            ]);
        }

        DB::transaction(function () use ($request, $transaction, $financialYear, $createIncome, $createExpense): void {
            $transaction->update([
                'financial_year_id' => $financialYear->getKey(),
                'transaction_date' => $request->string('transaction_date')->toString(),
                'direction' => $request->string('direction')->toString(),
                'amount' => $request->string('amount')->toString(),
                'description' => $request->string('description')->toString(),
                'ato_category' => $request->input('ato_category'),
                'property_id' => $request->input('property_id'),
            ]);

            DB::table('income')->where('transaction_id', $transaction->getKey())->delete();
            DB::table('expenses')->where('transaction_id', $transaction->getKey())->delete();

            $record = $transaction->direction === 'income' ? $createIncome : $createExpense;
            $record->execute(
                user: $request->user(),
                transaction: $transaction,
                amount: $transaction->amount,
                sourceSystem: $transaction->source_system ?? 'manual',
            );
        });

        return response()->json(['data' => $transaction->refresh()->load('document')]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $transaction = $this->findOwned($request, $id);

        DB::transaction(function () use ($transaction): void {
            $key = $transaction->getKey();

            DB::table('income')->where('transaction_id', $key)->delete();
            DB::table('expenses')->where('transaction_id', $key)->delete();
            DB::table('financial_record_documents')->where('transaction_id', $key)->delete();
            DB::table('reviews')->where('transaction_id', $key)->update(['transaction_id' => null]);
            $transaction->delete();
        });

        return response()->json(['message' => 'Transaction deleted.']);
    }

    private function findOwned(Request $request, string $id): Transaction
    {
        return Transaction::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }
}
