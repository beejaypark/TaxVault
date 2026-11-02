<?php

namespace App\Http\Controllers\Api;

use App\Application\Expenses\CreateExpense;
use App\Application\Income\CreateIncome;
use App\Application\Transactions\CreateTransaction;
use App\Domain\Transactions\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
