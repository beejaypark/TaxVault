<?php

namespace App\Application\FinancialYears;

use App\Application\Documents\DeleteDocument;
use App\Application\Documents\DocumentDeletionConflict;
use App\Application\Reviews\DeleteReview;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\AccountantGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteFinancialYear
{
    public function __construct(
        private readonly DeleteDocument $deleteDocument,
        private readonly DeleteReview $deleteReview,
    ) {}

    /**
     * Permanently removes a financial year and every record that belongs to it.
     *
     * @throws DocumentDeletionConflict when a document is still being processed
     */
    public function execute(User $user, FinancialYear $financialYear): void
    {
        DB::transaction(function () use ($user, $financialYear): void {
            $yearId = $financialYear->getKey();

            $documentIds = DB::table('documents')
                ->where('user_id', $user->getKey())
                ->where('financial_year_id', $yearId)
                ->pluck('id');

            foreach ($documentIds as $documentId) {
                $this->deleteDocument->execute($user, $documentId, $this->deleteReview);
            }

            $transactionIds = DB::table('transactions')
                ->where('financial_year_id', $yearId)
                ->pluck('id');
            $extractionIds = DB::table('document_extractions')
                ->where('source_financial_year_id', $yearId)
                ->pluck('id');

            $reviewIds = DB::table('reviews')
                ->where('source_financial_year_id', $yearId)
                ->orWhereIn('transaction_id', $transactionIds)
                ->orWhereIn('document_extraction_id', $extractionIds)
                ->pluck('id');

            DB::table('reviews')
                ->whereIn('supersedes_review_id', $reviewIds)
                ->update(['supersedes_review_id' => null]);
            DB::table('reviews')->whereIn('id', $reviewIds)->delete();

            DB::table('income')->where('financial_year_id', $yearId)->delete();
            DB::table('expenses')->where('financial_year_id', $yearId)->delete();
            DB::table('financial_record_documents')
                ->whereIn('transaction_id', $transactionIds)
                ->delete();
            DB::table('transactions')->where('financial_year_id', $yearId)->delete();
            DB::table('tax_evidences')->where('financial_year_id', $yearId)->delete();
            DB::table('document_extractions')->whereIn('id', $extractionIds)->delete();
            DB::table('financial_year_export_archives')
                ->where('financial_year_id', $yearId)
                ->delete();

            // An emptied list means "no years", never "all years".
            AccountantGrant::query()
                ->where('owner_id', $user->getKey())
                ->whereNotNull('financial_year_ids')
                ->get()
                ->each(function (AccountantGrant $grant) use ($yearId): void {
                    $ids = $grant->financial_year_ids ?? [];

                    if (in_array($yearId, $ids, true)) {
                        $grant->financial_year_ids = array_values(array_diff($ids, [$yearId]));
                        $grant->save();
                    }
                });

            $financialYear->delete();
        });
    }
}
