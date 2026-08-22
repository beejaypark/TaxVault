<?php

namespace App\Domain\Reporting\Services;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\DTOs\FinancialYearExport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class FinancialYearExportService
{
    private const EXPORT_VERSION = '1';

    public function export(
        User $user,
        string $financialYearId,
    ): FinancialYearExport {
        $financialYear = FinancialYear::query()
            ->whereKey($financialYearId)
            ->where('user_id', $user->getKey())
            ->first();

        if ($financialYear === null) {
            throw (new ModelNotFoundException)->setModel(
                FinancialYear::class,
                [$financialYearId],
            );
        }

        $transactions = DB::table('transactions')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $transactionIds = array_column($transactions, 'id');

        $expenses = DB::table('expenses')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $income = DB::table('income')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $documents = DB::table('documents')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $documentIds = array_column($documents, 'id');

        $documentExtractions = $this->whereInOrEmpty(
            'document_extractions',
            'document_id',
            $documentIds,
        );

        $evidenceLinks = $this->whereInOrEmpty(
            'financial_record_documents',
            'document_id',
            $documentIds,
        );

        $properties = DB::table('properties')
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $propertyIds = array_column($properties, 'id');

        $propertyPeriods = $this->whereInOrEmpty(
            'property_periods',
            'property_id',
            $propertyIds,
        );

        $investmentsQuery = DB::table('investments')
            ->where('user_id', $user->getKey());

        if ($propertyIds !== []) {
            $investmentsQuery->where(function ($query) use ($propertyIds): void {
                $query
                    ->whereIn('property_id', $propertyIds)
                    ->orWhereNull('property_id');
            });
        } else {
            $investmentsQuery->whereNull('property_id');
        }

        $investments = $investmentsQuery
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $reviews = $this->loadReviews(
            $documentIds,
            $transactionIds,
            array_column($documentExtractions, 'id'),
        );

        $auditEvents = $this->loadAuditEvents(
            $user,
            $documentIds,
            $transactionIds,
            array_column($documents, 'id'),
            array_column($reviews, 'id'),
        );

        $generatedAt = CarbonImmutable::now('UTC')->toIso8601String();

        $payload = [
            'manifest' => [
                'export_version' => self::EXPORT_VERSION,
                'generated_at' => $generatedAt,
                'financial_year_id' => $financialYear->getKey(),
                'year_code' => $financialYear->year_code,
                'record_counts' => [
                    'transactions' => count($transactions),
                    'expenses' => count($expenses),
                    'income' => count($income),
                    'documents' => count($documents),
                    'document_extractions' => count($documentExtractions),
                    'financial_record_documents' => count($evidenceLinks),
                    'properties' => count($properties),
                    'property_periods' => count($propertyPeriods),
                    'investments' => count($investments),
                    'reviews' => count($reviews),
                    'audit_events' => count($auditEvents),
                ],
            ],
            'financial_year' => $this->normalize((array) $financialYear->toArray()),
            'tax_records' => [
                'transactions' => $transactions,
                'expenses' => $expenses,
                'income' => $income,
            ],
            'source_references' => [
                'documents' => $documents,
                'document_extractions' => $documentExtractions,
            ],
            'evidence_chain' => [
                'financial_record_documents' => $evidenceLinks,
            ],
            'assets' => [
                'properties' => $properties,
                'property_periods' => $propertyPeriods,
                'investments' => $investments,
            ],
            'verification_history' => [
                'reviews' => $reviews,
            ],
            'audit' => [
                'events' => $auditEvents,
            ],
        ];

        $canonical = $this->canonicalJson($payload);
        $sha256 = hash('sha256', $canonical);

        $payload['manifest']['sha256'] = $sha256;

        $json = $this->canonicalJson($payload);

        return new FinancialYearExport(
            payload: $payload,
            json: $json,
            sha256: $sha256,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function whereInOrEmpty(
        string $table,
        string $column,
        array $ids,
    ): array {
        if ($ids === []) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $ids)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadReviews(
        array $documentIds,
        array $transactionIds,
        array $extractionIds,
    ): array {
        return DB::table('reviews')
            ->where(function ($query) use (
                $documentIds,
                $transactionIds,
                $extractionIds,
            ): void {
                if ($documentIds !== []) {
                    $query->whereIn('document_id', $documentIds);
                }

                if ($transactionIds !== []) {
                    $query->orWhereIn('transaction_id', $transactionIds);
                }

                if ($extractionIds !== []) {
                    $query->orWhereIn('document_extraction_id', $extractionIds);
                }
            })
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadAuditEvents(
        User $user,
        array $documentIds,
        array $transactionIds,
        array $financialRecordDocumentIds,
        array $reviewIds,
    ): array {
        return DB::table('audit_events')
            ->where(function ($query) use (
                $user,
                $documentIds,
                $transactionIds,
                $financialRecordDocumentIds,
                $reviewIds,
            ): void {
                $query->where('actor_user_id', $user->getKey());

                if ($documentIds !== []) {
                    $query->orWhere(function ($query) use ($documentIds): void {
                        $query
                            ->where('target_type', 'documents')
                            ->whereIn('target_id', $documentIds);
                    });
                }

                if ($transactionIds !== []) {
                    $query->orWhere(function ($query) use ($transactionIds): void {
                        $query
                            ->where('target_type', 'transactions')
                            ->whereIn('target_id', $transactionIds);
                    });
                }

                if ($financialRecordDocumentIds !== []) {
                    $query->orWhere(function ($query) use ($financialRecordDocumentIds): void {
                        $query
                            ->where('target_type', 'financial_record_documents')
                            ->whereIn('target_id', $financialRecordDocumentIds);
                    });
                }

                if ($reviewIds !== []) {
                    $query->orWhere(function ($query) use ($reviewIds): void {
                        $query
                            ->where('target_type', 'reviews')
                            ->whereIn('target_id', $reviewIds);
                    });
                }
            })
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function canonicalJson(array $payload): string
    {
        return json_encode(
            $this->normalize($payload),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR,
        );
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn ($item) => $this->normalize($item),
                $value,
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
