<?php

namespace App\Domain\Evidence\Services;

use App\Domain\Expenses\Models\Expense;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Income\Models\Income;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

class MissingEvidenceDetector
{
    /**
     * @return Collection<int, array{
     *     record_type: string,
     *     record_id: string,
     *     financial_year_id: string,
     *     financial_year_code: string,
     *     reason: string
     * }>
     */
    public function detect(
        User $user,
        ?FinancialYear $financialYear = null,
    ): Collection {
        $financialYearId = $financialYear?->getKey();

        $transactions = Transaction::query()
            ->with('financialYear:id,year_code')
            ->where('user_id', $user->getKey())
            ->when(
                $financialYearId,
                fn ($query) => $query->where('financial_year_id', $financialYearId)
            )
            ->whereNull('document_id')
            ->get();

        $expenses = Expense::query()
            ->with([
                'financialYear:id,year_code',
                'transaction:id,document_id',
            ])
            ->where('user_id', $user->getKey())
            ->when(
                $financialYearId,
                fn ($query) => $query->where('financial_year_id', $financialYearId)
            )
            ->whereHas('transaction', function ($query): void {
                $query->whereNull('document_id');
            })
            ->get();

        $income = Income::query()
            ->with([
                'financialYear:id,year_code',
                'transaction:id,document_id',
            ])
            ->where('user_id', $user->getKey())
            ->when(
                $financialYearId,
                fn ($query) => $query->where('financial_year_id', $financialYearId)
            )
            ->whereHas('transaction', function ($query): void {
                $query->whereNull('document_id');
            })
            ->get();

        return $transactions
            ->map(fn (Transaction $record): array => [
                'record_type' => 'transaction',
                'record_id' => (string) $record->getKey(),
                'financial_year_id' => $record->financial_year_id,
                'financial_year_code' => $record->financialYear->year_code,
                'reason' => 'missing_evidence',
            ])
            ->concat(
                $expenses->map(fn (Expense $record): array => [
                    'record_type' => 'expense',
                    'record_id' => (string) $record->getKey(),
                    'financial_year_id' => $record->financial_year_id,
                    'financial_year_code' => $record->financialYear->year_code,
                    'reason' => 'missing_evidence',
                ])
            )
            ->concat(
                $income->map(fn (Income $record): array => [
                    'record_type' => 'income',
                    'record_id' => (string) $record->getKey(),
                    'financial_year_id' => $record->financial_year_id,
                    'financial_year_code' => $record->financialYear->year_code,
                    'reason' => 'missing_evidence',
                ])
            )
            ->values();
    }
}
