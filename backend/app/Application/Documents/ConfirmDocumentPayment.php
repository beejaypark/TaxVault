<?php

namespace App\Application\Documents;

use App\Application\Expenses\CreateExpense;
use App\Application\Transactions\CreateTransaction;
use App\Domain\Documents\Models\Document;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmDocumentPayment
{
    public function __construct(
        private readonly CreateTransaction $createTransaction,
        private readonly CreateExpense $createExpense,
    ) {}

    public function execute(
        User $user,
        string $documentId,
        string $paymentDate,
        string $paymentMethod,
        string $amount,
    ): Document {
        return DB::transaction(function () use (
            $user,
            $documentId,
            $paymentDate,
            $paymentMethod,
            $amount,
        ): Document {
            $document = Document::query()
                ->whereKey($documentId)
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                throw (new ModelNotFoundException)->setModel(
                    Document::class,
                    [$documentId],
                );
            }

            if ($document->financial_year_id === null) {
                throw ValidationException::withMessages([
                    'document' => 'Assign this document to a financial year before confirming its payment.',
                ]);
            }

            if ($document->payment_status === 'paid') {
                throw ValidationException::withMessages([
                    'document' => 'Payment has already been confirmed for this document.',
                ]);
            }

            $existingTransactions = Transaction::query()
                ->where('user_id', $user->getKey())
                ->where('document_id', $document->getKey())
                ->where('direction', 'expense')
                ->lockForUpdate()
                ->get();

            if ($existingTransactions->count() > 1) {
                throw ValidationException::withMessages([
                    'document' => 'More than one expense is already linked to this document. Review the existing transactions before confirming payment.',
                ]);
            }

            $paymentProvenance = [
                'payment_confirmation' => [
                    'method' => $paymentMethod,
                    'confirmed_by_user' => true,
                ],
            ];

            if ($existingTransactions->isEmpty()) {
                $transaction = $this->createTransaction->execute(
                    user: $user,
                    transactionDate: $paymentDate,
                    direction: 'expense',
                    amount: $amount,
                    currency: 'AUD',
                    documentId: $document->getKey(),
                    description: 'Payment for '.$document->original_filename,
                    sourceSystem: 'document_payment_confirmation',
                    provenance: $paymentProvenance,
                );

                $this->createExpense->execute(
                    user: $user,
                    transaction: $transaction,
                    amount: $amount,
                    sourceSystem: 'document_payment_confirmation',
                    metadata: [
                        'document_id' => $document->getKey(),
                        'payment_method' => $paymentMethod,
                    ],
                );
            } else {
                $transaction = $existingTransactions->sole();
                if (bccomp((string) $transaction->amount, $amount, 2) !== 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'An expense is already linked to this document with a different amount. Reconcile it before confirming payment to avoid a duplicate or incorrect record.',
                    ]);
                }

                $transaction->update([
                    'provenance' => array_merge(
                        $transaction->provenance ?? [],
                        $paymentProvenance,
                    ),
                ]);
            }

            $document->update([
                'payment_status' => 'paid',
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'payment_amount' => $amount,
            ]);

            return $document->fresh('latestExtraction');
        });
    }
}
