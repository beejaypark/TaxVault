<?php

namespace App\Domain\Reporting\Services;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;

final class TransactionCsvExport
{
    private const HEADERS = [
        'Date',
        'Type',
        'Description',
        'Amount',
        'Currency',
        'Document',
        'Payment status',
        'Payment method',
        'Source',
    ];

    public function write(User $user, FinancialYear $financialYear): void
    {
        $output = fopen('php://output', 'w');
        // UTF-8 BOM so Excel detects the encoding.
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, self::HEADERS);

        Transaction::query()
            ->with('document')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->chunk(500, function ($transactions) use ($output): void {
                foreach ($transactions as $transaction) {
                    fputcsv($output, array_map($this->safeCell(...), [
                        $transaction->transaction_date?->format('Y-m-d'),
                        $transaction->direction,
                        $transaction->description,
                        $transaction->amount,
                        $transaction->currency,
                        $transaction->document?->original_filename,
                        $transaction->document?->payment_status,
                        $transaction->document?->payment_method,
                        $transaction->source_system,
                    ]));
                }
            });

        fclose($output);
    }

    // Prevent spreadsheet formula injection from user- or OCR-supplied text.
    private function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return $text !== '' && str_contains("=+-@\t\r", $text[0])
            ? "'".$text
            : $text;
    }
}
