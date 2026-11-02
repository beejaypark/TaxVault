<?php

namespace App\Application\Documents;

use App\Application\FinancialYears\CreateFinancialYear;
use App\Application\OCR\Data\NormalizedExtraction;
use App\Application\Reviews\Services\CreateReviewForDocumentExtraction;
use App\Domain\Documents\Models\Document;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AssignDocumentFinancialYear
{
    public function __construct(
        private readonly CreateFinancialYear $createFinancialYear,
        private readonly CreateReviewForDocumentExtraction $createReview,
    ) {}

    public function execute(
        User $user,
        string $documentId,
        ?string $confirmedYearCode,
        ?string $financialYearId,
    ): Document {
        return DB::transaction(function () use (
            $user,
            $documentId,
            $confirmedYearCode,
            $financialYearId,
        ): Document {
            User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $document = Document::query()
                ->whereKey($documentId)
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($document->financial_year_id !== null) {
                throw new FinancialYearSuggestionUnavailable(
                    'This document already has a financial year.'
                );
            }

            $extraction = $document->latestExtraction;
            if ($extraction === null || $extraction->status !== 'completed') {
                throw new FinancialYearSuggestionUnavailable(
                    'Document extraction must complete before assigning a suggested financial year.'
                );
            }

            $normalized = $extraction->output['normalized'] ?? null;
            $fields = is_array($normalized) ? ($normalized['fields'] ?? []) : [];
            if ($financialYearId !== null) {
                $financialYear = FinancialYear::query()
                    ->whereKey($financialYearId)
                    ->where('user_id', $user->getKey())
                    ->where('status', 'active')
                    ->first();

                if ($financialYear === null) {
                    throw new FinancialYearSuggestionUnavailable(
                        'The selected financial year is not available.'
                    );
                }
            } else {
                $dateValue = $fields['invoice_date']['value'] ?? null;
                if (! is_string($dateValue)) {
                    throw new FinancialYearSuggestionUnavailable(
                        'No document date was found. Select an existing financial year or create one manually.'
                    );
                }

                $date = CarbonImmutable::createFromFormat('!Y-m-d', $dateValue);
                if ($date === false || $date->format('Y-m-d') !== $dateValue) {
                    throw new FinancialYearSuggestionUnavailable(
                        'The extracted document date is invalid.'
                    );
                }

                $startYear = $date->month >= 7 ? $date->year : $date->year - 1;
                $suggestedYearCode = sprintf(
                    '%04d-%02d',
                    $startYear,
                    ($startYear + 1) % 100,
                );

                if ($confirmedYearCode !== $suggestedYearCode) {
                    throw new FinancialYearSuggestionUnavailable(
                        'The confirmed financial year does not match the extracted document date.'
                    );
                }

                $financialYear = FinancialYear::query()
                    ->where('user_id', $user->getKey())
                    ->where('year_code', $suggestedYearCode)
                    ->where('status', 'active')
                    ->first();

                if ($financialYear === null) {
                    $financialYear = $this->createFinancialYear->execute(
                        user: $user,
                        yearCode: $suggestedYearCode,
                        startDate: sprintf('%04d-07-01', $startYear),
                        endDate: sprintf('%04d-06-30', $startYear + 1),
                    );
                }
            }

            $document->financial_year_id = $financialYear->getKey();
            $document->save();

            $fields = is_array($normalized) ? ($normalized['fields'] ?? []) : [];
            $this->createReview->execute(
                $document,
                $extraction,
                new NormalizedExtraction(
                    schemaVersion: is_array($normalized)
                        ? (string) ($normalized['schema_version'] ?? '')
                        : '',
                    fields: $fields,
                ),
            );
            $document->update(['status' => 'review_required']);

            return $document->fresh('latestExtraction');
        });
    }
}
