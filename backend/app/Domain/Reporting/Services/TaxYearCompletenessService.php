<?php

namespace App\Domain\Reporting\Services;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\DTOs\TaxYearCompleteness;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaxYearCompletenessService
{
    public function calculate(
        User $user,
        FinancialYear $financialYear,
    ): TaxYearCompleteness {
        if ($financialYear->user_id !== $user->getKey()) {
            abort(404);
        }

        $financialYearId = $financialYear->getKey();

        $income = DB::table('income')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYearId)
            ->selectRaw('COUNT(*) as records, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $expenses = DB::table('expenses')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYearId)
            ->selectRaw('COUNT(*) as records, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $transactions = Transaction::query()
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYearId)
            ->get([
                'id',
                'document_id',
            ]);

        $transactionRecords = $transactions->count();

        $evidenceLinked = $transactions
            ->whereNotNull('document_id')
            ->count();

        $evidenceMissing = $transactions
            ->whereNull('document_id')
            ->count();

        /*
         * Reviews are attached to documents, extractions or transactions.
         * For FY completeness, transaction-targeted reviews are directly
         * scoped by financial year. Document-targeted reviews are scoped
         * through the transaction's document association.
         */
        $reviewedTransactionIds = DB::table('reviews')
            ->join(
                'transactions',
                'transactions.id',
                '=',
                'reviews.transaction_id'
            )
            ->where('transactions.user_id', $user->getKey())
            ->where('transactions.financial_year_id', $financialYearId)
            ->where('reviews.status', 'completed')
            ->whereNotNull('reviews.transaction_id')
            ->distinct()
            ->pluck('transactions.id');

        $verifiedRecords = $reviewedTransactionIds->count();

        $unverifiedRecords = max(
            0,
            $transactionRecords - $verifiedRecords
        );

        /*
         * A property period belongs to the FY when its period overlaps
         * the selected 1 July -> 30 June period.
         */
        $propertyPeriods = DB::table('property_periods')
            ->join(
                'properties',
                'properties.id',
                '=',
                'property_periods.property_id'
            )
            ->where('properties.user_id', $user->getKey())
            ->whereDate(
                'property_periods.period_start',
                '<=',
                $financialYear->end_date
            )
            ->where(function ($query) use ($financialYear): void {
                $query
                    ->whereNull('property_periods.period_end')
                    ->orWhereDate(
                        'property_periods.period_end',
                        '>=',
                        $financialYear->start_date
                    );
            })
            ->count();

        $gaps = [];

        if ($income->records === 0) {
            $gaps[] = [
                'type' => 'income',
                'code' => 'no_income_records',
                'message' => 'No income records exist for this financial year.',
            ];
        }

        if ($expenses->records === 0) {
            $gaps[] = [
                'type' => 'expenses',
                'code' => 'no_expense_records',
                'message' => 'No expense records exist for this financial year.',
            ];
        }

        if ($evidenceMissing > 0) {
            $gaps[] = [
                'type' => 'evidence',
                'code' => 'missing_evidence',
                'count' => $evidenceMissing,
                'message' => 'One or more transactions have no linked evidence.',
            ];
        }

        if ($unverifiedRecords > 0) {
            $gaps[] = [
                'type' => 'verification',
                'code' => 'unverified_records',
                'count' => $unverifiedRecords,
                'message' => 'One or more transactions have not been verified.',
            ];
        }

        return new TaxYearCompleteness(
            financialYearId: $financialYearId,
            financialYearCode: $financialYear->year_code,

            incomeRecords: (int) $income->records,
            expenseRecords: (int) $expenses->records,

            transactionRecords: $transactionRecords,

            evidenceLinked: $evidenceLinked,
            evidenceMissing: $evidenceMissing,

            verifiedRecords: $verifiedRecords,
            unverifiedRecords: $unverifiedRecords,

            propertyPeriods: $propertyPeriods,

            incomeAmount: (float) $income->amount,
            expenseAmount: (float) $expenses->amount,

            gaps: $gaps,
        );
    }
}
