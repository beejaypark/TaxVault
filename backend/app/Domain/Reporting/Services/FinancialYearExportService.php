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
    private const EXPORT_VERSION = '2';

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

        $mainResidencePropertyIds = DB::table('properties')
            ->where('user_id', $user->getKey())
            ->where('is_main_residence', true)
            ->pluck('id')
            ->all();

        $transactionsQuery = DB::table('transactions')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ;
        if ($mainResidencePropertyIds !== []) {
            $transactionsQuery->where(function ($query) use ($mainResidencePropertyIds): void {
                $query->whereNull('property_id')->orWhereNotIn('property_id', $mainResidencePropertyIds);
            });
        }
        $transactions = $transactionsQuery->orderBy('id')->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $transactionIds = array_column($transactions, 'id');

        $expenses = DB::table('expenses')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ->whereIn('transaction_id', $transactionIds)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $income = DB::table('income')
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ->whereIn('transaction_id', $transactionIds)
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

        $documentExtractions = DB::table('document_extractions')
            ->where(function ($query) use ($documentIds, $financialYear): void {
                if ($documentIds !== []) {
                    $query->whereIn('document_id', $documentIds);
                }

                $query->orWhere('source_financial_year_id', $financialYear->getKey());
            })
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $evidenceLinks = $documentIds === [] && $transactionIds === []
            ? []
            : DB::table('financial_record_documents')
                ->where(function ($query) use ($documentIds, $transactionIds): void {
                    if ($documentIds !== []) {
                        $query->whereIn('document_id', $documentIds);
                    }

                    if ($transactionIds !== []) {
                        $query->orWhere(function ($query) use ($transactionIds): void {
                            $query
                                ->whereIn('transaction_id', $transactionIds)
                                ->whereNull('document_id');
                        });
                    }
                })
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

        $properties = DB::table('properties')
            ->where('user_id', $user->getKey())
            ->where(function ($query): void {
                $query->whereNull('is_main_residence')->orWhere('is_main_residence', false);
            })
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

        $propertyDeductions = $propertyIds === []
            ? []
            : DB::table('property_deductions')
                ->leftJoin('documents as source_documents', 'source_documents.id', '=', 'property_deductions.source_document_id')
                ->whereIn('property_deductions.property_id', $propertyIds)
                ->where('property_deductions.financial_year_id', $financialYear->getKey())
                ->select('property_deductions.*', 'source_documents.original_filename as source_document_filename', 'source_documents.description as source_document_description')
                ->orderBy('property_deductions.id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

        $mortgages = $this->whereInOrEmpty('mortgages', 'property_id', $propertyIds);
        $mortgageIds = array_column($mortgages, 'id');
        $mortgageInterest = $mortgageIds === []
            ? []
            : DB::table('mortgage_interest_entries')
                ->join('mortgages', 'mortgages.id', '=', 'mortgage_interest_entries.mortgage_id')
                ->join('properties', 'properties.id', '=', 'mortgages.property_id')
                ->whereIn('mortgage_id', $mortgageIds)
                ->whereBetween('month', [$financialYear->start_date->toDateString(), $financialYear->end_date->toDateString()])
                ->select(
                    'mortgage_interest_entries.*',
                    'mortgages.bank_name',
                    'mortgages.property_id',
                    'properties.reference_code as property_reference_code',
                )
                ->orderBy('month')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

        // Some users record loan interest as a classified rental expense rather
        // than as a mortgage schedule entry. Keep the accountant pack useful in
        // that case, while preferring the dedicated schedule when it exists.
        if ($mortgageInterest === [] && $transactionIds !== []) {
            $mortgageInterest = DB::table('expenses')
                ->join('transactions', 'transactions.id', '=', 'expenses.transaction_id')
                ->leftJoin('properties', 'properties.id', '=', 'transactions.property_id')
                ->where('expenses.user_id', $user->getKey())
                ->where('expenses.financial_year_id', $financialYear->getKey())
                ->whereIn('expenses.transaction_id', $transactionIds)
                ->where('transactions.ato_category', 'rental_interest')
                ->select(
                    'expenses.id',
                    'expenses.amount as interest_amount',
                    'transactions.transaction_date as month',
                    'transactions.property_id',
                    'properties.reference_code as property_reference_code',
                    DB::raw('NULL as bank_name'),
                    DB::raw('NULL as mortgage_id'),
                )
                ->orderBy('transactions.transaction_date')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }
        $mortgageRateChanges = $mortgageIds === []
            ? []
            : DB::table('mortgage_rate_changes')
                ->whereIn('mortgage_id', $mortgageIds)
                ->where('effective_date', '<=', $financialYear->end_date->toDateString())
                ->orderBy('effective_date')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

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
            ->whereDate('acquisition_date', '<=', $financialYear->end_date->toDateString())
            ->where(function ($query) use ($financialYear): void {
                $query->whereNull('disposal_date')->orWhereDate('disposal_date', '>=', $financialYear->start_date->toDateString());
            })
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $investmentIds = array_column($investments, 'id');
        $assetValuations = ($propertyIds === [] && $investmentIds === [])
            ? []
            : DB::table('asset_valuations')
                ->where('user_id', $user->getKey())
                ->where(function ($query) use ($propertyIds, $investmentIds): void {
                    if ($propertyIds !== []) $query->whereIn('property_id', $propertyIds);
                    if ($investmentIds !== []) {
                        $method = $propertyIds !== [] ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('investment_id', $investmentIds);
                    }
                })
                ->whereDate('as_of', '<=', $financialYear->end_date->toDateString())
                ->orderBy('as_of')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

        $reviews = $this->loadReviews(
            $documentIds,
            $transactionIds,
            array_column($documentExtractions, 'id'),
            $financialYear->getKey(),
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
                    'property_deductions' => count($propertyDeductions),
                    'mortgages' => count($mortgages),
                    'mortgage_interest_entries' => count($mortgageInterest),
                    'mortgage_rate_changes' => count($mortgageRateChanges),
                    'asset_valuations' => count($assetValuations),
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
                'property_deductions' => $propertyDeductions,
                'mortgages' => $mortgages,
                'mortgage_interest_entries' => $mortgageInterest,
                'mortgage_rate_changes' => $mortgageRateChanges,
                'asset_valuations' => $assetValuations,
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
        array $where = [],
    ): array {
        if ($ids === []) {
            return [];
        }

        $query = DB::table($table)->whereIn($column, $ids);
        foreach ($where as $whereColumn => $whereValue) {
            $query->where($whereColumn, $whereValue);
        }

        return $query
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
        string $financialYearId,
    ): array {
        return DB::table('reviews')
            ->where(function ($query) use (
                $documentIds,
                $transactionIds,
                $extractionIds,
                $financialYearId,
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

                $query->orWhere('source_financial_year_id', $financialYearId);
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
