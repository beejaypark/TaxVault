<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\DTOs\TaxReadiness;
use App\Domain\Transactions\AtoCategory;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TaxReadinessService
{
    public const CALCULATION_VERSION = 'v3';

    /** @var array<string, int> */
    private const WEIGHTS = [
        'documents' => 30,
        'transactions' => 25,
        'evidence' => 25,
        'required_data' => 20,
    ];

    public function calculate(User $user, FinancialYear $financialYear): TaxReadiness
    {
        if ($financialYear->user_id !== $user->getKey()) {
            abort(404);
        }

        $documents = Document::query()
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ->with(['latestExtraction:id,document_id,status,created_at'])
            ->get([
                'id',
                'document_type_id',
                'financial_year_id',
                'object_key',
                'size_bytes',
            ]);

        $documentIds = collect($documents->modelKeys());
        $transactions = Transaction::query()
            ->forTaxReturn()
            ->where('user_id', $user->getKey())
            ->where('financial_year_id', $financialYear->getKey())
            ->get([
                'id',
                'document_id',
                'ato_category',
                'transaction_date',
                'amount',
                'direction',
                'description',
                'property_id',
            ]);

        $reviewedDocumentIds = $this->reviewedDocumentIds($documentIds);
        $documentTotal = $documents->count();
        $transactionTotal = $transactions->count();
        $evidenceRequiredTransactions = $transactions->filter(fn (Transaction $transaction): bool => $this->transactionRequiresEvidence($transaction));

        $failedExtractionCount = $documents
            ->filter(fn (Document $document): bool => $document->latestExtraction?->status === 'failed')
            ->count();

        $components = [
            'documents' => $this->component(
                'Documents reviewed',
                $reviewedDocumentIds->count(),
                $documentTotal,
            ),
            'transactions' => $this->component(
                'Transactions categorised',
                $transactions->whereNotNull('ato_category')->count(),
                $transactionTotal,
            ),
            'evidence' => $this->component(
                'Evidence linked',
                $evidenceRequiredTransactions->whereNotNull('document_id')->count(),
                $evidenceRequiredTransactions->count(),
            ),
            'required_data' => $this->component(
                'Required data complete',
                $documents->filter(fn (Document $document): bool => $document->document_type_id !== null
                    && $document->financial_year_id !== null
                    && $document->object_key !== null
                )->count() + $transactions->filter(fn (Transaction $transaction): bool => $this->transactionRequiredDataComplete($transaction)
                )->count(),
                $documentTotal + $transactionTotal,
            ),
        ];

        $issues = [];
        $unreviewed = max(0, $documentTotal - $reviewedDocumentIds->count());
        $uncategorised = $transactions->whereNull('ato_category')->count();
        $missingEvidence = $evidenceRequiredTransactions->whereNull('document_id')->count();
        $incompleteRequiredData = ($documentTotal + $transactionTotal)
            - $components['required_data']['completed'];

        if ($failedExtractionCount > 0) {
            $issues[] = [
                'severity' => 'blocked',
                'code' => 'failed_extraction',
                'count' => $failedExtractionCount,
                'message' => 'One or more documents failed text extraction.',
            ];
        }
        if ($unreviewed > 0) {
            $issues[] = [
                'severity' => 'blocked',
                'code' => 'unreviewed_documents',
                'count' => $unreviewed,
                'message' => 'One or more documents have not been reviewed.',
            ];
        }
        if ($uncategorised > 0) {
            $issues[] = [
                'severity' => 'blocked',
                'code' => 'uncategorised_transactions',
                'count' => $uncategorised,
                'message' => 'One or more transactions have no tax category.',
            ];
        }
        if ($missingEvidence > 0) {
            $issues[] = [
                'severity' => 'needs_attention',
                'code' => 'missing_evidence',
                'count' => $missingEvidence,
                'message' => 'One or more transactions have no linked evidence.',
            ];
        }
        if ($incompleteRequiredData > 0) {
            $issues[] = [
                'severity' => 'blocked',
                'code' => 'incomplete_required_data',
                'count' => $incompleteRequiredData,
                'message' => 'Required tax data is missing or invalid.',
            ];
        }

        $score = $this->score($components);

        return new TaxReadiness(
            financialYearId: $financialYear->getKey(),
            financialYearCode: $financialYear->year_code,
            score: $score,
            status: $score === null ? 'not_started' : ($issues === [] ? 'ready' : 'needs_attention'),
            calculationVersion: self::CALCULATION_VERSION,
            components: $components,
            issues: $issues,
        );
    }

    private function transactionRequiredDataComplete(Transaction $transaction): bool
    {
        if ($transaction->transaction_date === null
            || $transaction->amount === null
            || (float) $transaction->amount <= 0
            || ! in_array($transaction->direction, ['income', 'expense'], true)
            || trim((string) $transaction->description) === '') {
            return false;
        }

        return ! is_string($transaction->ato_category)
            || ! AtoCategory::isRental($transaction->ato_category)
            || $transaction->property_id !== null;
    }

    private function transactionRequiresEvidence(Transaction $transaction): bool
    {
        // A transaction must first be categorised before we can determine the
        // evidence expected for it. This avoids reporting the same row twice.
        return is_string($transaction->ato_category) && $transaction->ato_category !== '';
    }

    /** @param Collection<int, string> $documentIds */
    private function reviewedDocumentIds($documentIds)
    {
        if ($documentIds->isEmpty()) {
            return collect();
        }

        $direct = DB::table('reviews')
            ->where('status', 'completed')
            ->whereIn('document_id', $documentIds)
            ->pluck('document_id');

        $fromExtractions = DB::table('reviews')
            ->join('document_extractions', 'document_extractions.id', '=', 'reviews.document_extraction_id')
            ->where('reviews.status', 'completed')
            ->whereIn('document_extractions.document_id', $documentIds)
            ->pluck('document_extractions.document_id');

        return $direct->merge($fromExtractions)->unique()->values();
    }

    /** @return array{label: string, completed: int, total: int, percentage: int|null, applicable: bool} */
    private function component(string $label, int $completed, int $total): array
    {
        return [
            'label' => $label,
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : null,
            'applicable' => $total > 0,
        ];
    }

    /** @param array<string, array{percentage: int|null, applicable: bool}> $components */
    private function score(array $components): ?int
    {
        $weighted = 0.0;
        $weight = 0;

        foreach (self::WEIGHTS as $key => $componentWeight) {
            if (! $components[$key]['applicable'] || $components[$key]['percentage'] === null) {
                continue;
            }

            $weighted += $components[$key]['percentage'] * $componentWeight;
            $weight += $componentWeight;
        }

        return $weight > 0 ? (int) round($weighted / $weight) : null;
    }
}
