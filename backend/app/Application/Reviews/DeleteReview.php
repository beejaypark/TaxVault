<?php

namespace App\Application\Reviews;

use App\Domain\Documents\Models\Document;
use App\Domain\Reviews\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DeleteReview
{
    public function execute(User $user, string $reviewId): void
    {
        DB::transaction(function () use ($user, $reviewId): void {
            $review = Review::query()
                ->whereKey($reviewId)
                ->where('reviewer_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($review === null) {
                throw (new ModelNotFoundException)->setModel(
                    Review::class,
                    [$reviewId],
                );
            }

            $this->deleteReviews(collect([$review->getKey()]));
        });
    }

    public function deleteForDocument(Document $document): void
    {
        $documentId = $document->getKey();
        $extractionIds = DB::table('document_extractions')
            ->where('document_id', $documentId)
            ->pluck('id');
        $transactionIds = DB::table('transactions')
            ->where(function ($query) use ($documentId): void {
                $query->where('document_id', $documentId)
                    ->orWhere('source_document_id', $documentId);
            })
            ->pluck('id');

        $reviewIds = Review::query()
            ->where(function ($query) use ($documentId, $extractionIds, $transactionIds): void {
                $query->where('document_id', $documentId)
                    ->orWhere('source_document_id', $documentId)
                    ->orWhereIn('document_extraction_id', $extractionIds)
                    ->orWhereIn('transaction_id', $transactionIds);
            })
            ->pluck('id');

        $this->deleteReviews($reviewIds);
    }

    /**
     * @param Collection<int, string> $reviewIds
     */
    private function deleteReviews(Collection $reviewIds): void
    {
        if ($reviewIds->isEmpty()) {
            return;
        }

        Review::query()
            ->whereIn('supersedes_review_id', $reviewIds)
            ->update(['supersedes_review_id' => null]);

        Review::query()
            ->whereIn('id', $reviewIds)
            ->delete();
    }
}
