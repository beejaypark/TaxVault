<?php

namespace App\Application\Transactions;

use App\Domain\FinancialYears\Services\FinancialYearResolver;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTransaction
{
    public function __construct(
        private readonly FinancialYearResolver $financialYearResolver,
    ) {}

    public function execute(
        User $user,
        string $transactionDate,
        string $direction,
        string $amount,
        string $currency = 'AUD',
        ?string $documentId = null,
        ?string $description = null,
        ?string $settlementDate = null,
        ?string $sourceSystem = null,
        ?string $externalTransactionId = null,
        ?array $provenance = null,
        ?string $taxCategoryId = null,
    ): Transaction {
        return DB::transaction(function () use (
            $user,
            $transactionDate,
            $direction,
            $amount,
            $currency,
            $documentId,
            $description,
            $settlementDate,
            $sourceSystem,
            $externalTransactionId,
            $provenance,
            $taxCategoryId,
        ): Transaction {
            $financialYear = $this->financialYearResolver->resolve(
                $user->getKey(),
                $transactionDate,
            );

            return Transaction::create([
                'user_id' => $user->getKey(),
                'financial_year_id' => $financialYear->getKey(),
                'document_id' => $documentId,
                'transaction_date' => $transactionDate,
                'settlement_date' => $settlementDate,
                'description' => $description,
                'direction' => $direction,
                'amount' => $amount,
                'currency' => $currency,
                'source_system' => $sourceSystem,
                'external_transaction_id' => $externalTransactionId,
                'provenance' => $provenance,
                'tax_category_id' => $taxCategoryId,
            ]);
        });
    }
}
