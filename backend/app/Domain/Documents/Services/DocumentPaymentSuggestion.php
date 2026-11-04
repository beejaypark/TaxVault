<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Transactions\Models\Transaction;

final class DocumentPaymentSuggestion
{
    private const AMOUNT_FIELDS = ['total_amount', 'transaction.total'];

    private const DATE_FIELDS = ['invoice_date', 'transaction.date'];

    /**
     * Prefer values the user already reviewed (linked expense, then corrections)
     * over values detected by OCR.
     *
     * @return array{date: string|null, amount: string|null}
     */
    public function forDocument(Document $document): array
    {
        $transaction = Transaction::query()
            ->where('document_id', $document->getKey())
            ->where('direction', 'expense')
            ->orderBy('created_at')
            ->first();

        $corrections = TaxEvidence::query()
            ->where('document_id', $document->getKey())
            ->where('evidence_type', 'review_correction')
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get()
            ->mapWithKeys(fn (TaxEvidence $evidence): array => [
                $evidence->field_path => $evidence->extracted_value['value'] ?? null,
            ])
            ->all();

        $fields = data_get($document->latestExtraction?->output, 'normalized.fields', []);
        $fields = is_array($fields) ? $fields : [];
        $detected = array_map(
            static fn (mixed $field): mixed => is_array($field) ? ($field['value'] ?? null) : null,
            $fields,
        );

        return [
            'date' => $this->normalizeDate($transaction?->transaction_date?->format('Y-m-d'))
                ?? $this->firstDate($corrections)
                ?? $this->firstDate($detected),
            'amount' => $this->normalizeAmount($transaction?->amount)
                ?? $this->firstAmount($corrections)
                ?? $this->firstAmount($detected)
                ?? $this->amountFromText($detected['document_text'] ?? null),
        ];
    }

    /** @param array<string, mixed> $values */
    private function firstDate(array $values): ?string
    {
        foreach (self::DATE_FIELDS as $path) {
            $date = $this->normalizeDate($values[$path] ?? null);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $values */
    private function firstAmount(array $values): ?string
    {
        foreach (self::AMOUNT_FIELDS as $path) {
            $amount = $this->normalizeAmount($values[$path] ?? null);
            if ($amount !== null) {
                return $amount;
            }
        }

        return null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }

    private function normalizeAmount(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = str_replace([',', '$', ' '], '', $value);
        }

        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function amountFromText(mixed $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $matched = preg_match(
            '/(?:net(?:t)?\s+amount\s+payable|total\s+(?:amount\s+)?due|amount\s+due|pay\s+this\s+amount|total\s+payable|balance\s+due)\s*:?\s*\$?\s*(\d{1,3}(?:,\d{3})*\.\d{2}|\d+\.\d{2})/i',
            $text,
            $matches,
        );

        return $matched === 1 ? $this->normalizeAmount($matches[1]) : null;
    }
}
