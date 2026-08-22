<?php

namespace App\Application\Expenses;

use App\Domain\Expenses\Models\Expense;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateExpense
{
    public function execute(
        User $user,
        Transaction $transaction,
        string $amount,
        ?string $taxCategoryId = null,
        ?string $taxSubcategoryId = null,
        ?string $sourceSystem = null,
        ?string $externalId = null,
        ?array $metadata = null,
    ): Expense {
        if ($transaction->user_id !== $user->getKey()) {
            throw new LogicException('Transaction does not belong to the authenticated user.');
        }

        return DB::transaction(function () use (
            $user,
            $transaction,
            $amount,
            $taxCategoryId,
            $taxSubcategoryId,
            $sourceSystem,
            $externalId,
            $metadata,
        ): Expense {
            return Expense::create([
                'transaction_id' => $transaction->getKey(),
                'user_id' => $user->getKey(),
                'financial_year_id' => $transaction->financial_year_id,
                'tax_category_id' => $taxCategoryId,
                'tax_subcategory_id' => $taxSubcategoryId,
                'amount' => $amount,
                'source_system' => $sourceSystem,
                'external_id' => $externalId,
                'metadata' => $metadata,
            ]);
        });
    }
}
