<?php

namespace App\Http\Controllers\Api;

use App\Application\Expenses\CreateExpense;
use App\Application\Income\CreateIncome;
use App\Application\Reviews\Services\CreateAuthoritativeCorrection;
use App\Application\Transactions\CreateTransaction;
use App\Domain\Reviews\Models\Review;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreReviewCorrectionRequest;
use App\Http\Requests\Reviews\StoreReviewDecisionRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'financial_year_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $reviews = Review::query()
            ->with([
                'document',
                'extraction',
                'corrections',
            ])
            ->where(
                'reviewer_id',
                $request->user()->getKey()
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->when(
                $request->filled('review_type'),
                fn ($query) => $query->where(
                    'review_type',
                    $request->string('review_type')->toString()
                )
            )
            ->when(
                $request->filled('financial_year_id'),
                function ($query) use ($request): void {
                    $financialYearId = $request
                        ->string('financial_year_id')
                        ->toString();

                    $query->where(function ($targetQuery) use ($financialYearId): void {
                        $targetQuery
                            ->whereHas(
                                'document',
                                fn ($documentQuery) => $documentQuery->where(
                                    'financial_year_id',
                                    $financialYearId
                                )
                            )
                            ->orWhereHas(
                                'transaction',
                                fn ($transactionQuery) => $transactionQuery->where(
                                    'financial_year_id',
                                    $financialYearId
                                )
                            )
                            ->orWhereHas(
                                'corrections',
                                fn ($evidenceQuery) => $evidenceQuery->where(
                                    'financial_year_id',
                                    $financialYearId
                                )
                            );
                    });
                }
            )
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $reviews,
        ]);
    }

    public function show(
        Request $request,
        string $id,
    ): JsonResponse {
        $review = Review::query()
            ->with([
                'document',
                'extraction',
                'corrections',
            ])
            ->whereKey($id)
            ->where(
                'reviewer_id',
                $request->user()->getKey()
            )
            ->first();

        if ($review === null) {
            throw (new ModelNotFoundException)->setModel(
                Review::class,
                [$id],
            );
        }

        return response()->json([
            'data' => $review,
        ]);
    }

    public function decision(
        StoreReviewDecisionRequest $request,
        string $id,
        CreateTransaction $createTransaction,
        CreateIncome $createIncome,
        CreateExpense $createExpense,
    ): JsonResponse {
        $review = Review::query()
            ->whereKey($id)
            ->where(
                'reviewer_id',
                $request->user()->getKey()
            )
            ->first();

        if ($review === null) {
            throw (new ModelNotFoundException)->setModel(
                Review::class,
                [$id],
            );
        }

        if ($review->status !== 'pending') {
            return response()->json([
                'message' => 'Review is not pending.',
            ], 409);
        }

        DB::transaction(function () use (
            $request,
            $review,
            $createTransaction,
            $createIncome,
            $createExpense,
        ): void {
            $transactionId = $review->transaction_id;
            $expenseDetails = $request->validated('expense');

            if (
                $request->string('decision')->toString() === 'approve'
                && is_array($expenseDetails)
            ) {
                if ($review->document === null) {
                    abort(422, 'A source document is required to record an expense from a review.');
                }

                $transaction = $createTransaction->execute(
                    user: $request->user(),
                    transactionDate: $expenseDetails['transaction_date'],
                    direction: $expenseDetails['direction'],
                    amount: (string) $expenseDetails['amount'],
                    currency: 'AUD',
                    documentId: $review->document_id,
                    description: $expenseDetails['description'],
                    sourceSystem: 'document_review',
                    provenance: [
                        'review_id' => $review->getKey(),
                        'extraction_id' => $review->document_extraction_id,
                    ],
                );

                if ($transaction->direction === 'income') {
                    $createIncome->execute(
                        user: $request->user(),
                        transaction: $transaction,
                        amount: $transaction->amount,
                        sourceSystem: 'document_review',
                        metadata: ['review_id' => $review->getKey()],
                    );
                } else {
                    $createExpense->execute(
                        user: $request->user(),
                        transaction: $transaction,
                        amount: $transaction->amount,
                        sourceSystem: 'document_review',
                        metadata: ['review_id' => $review->getKey()],
                    );
                }

                $transactionId = $transaction->getKey();
            }

            $review->update([
                'status' => 'completed',
                'decision' => $request->string('decision')->toString(),
                'reviewer_notes' => $request->input(
                    'reviewer_notes',
                    $review->reviewer_notes
                ),
                'transaction_id' => $transactionId,
                'completed_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $review->fresh([
                'document',
                'extraction',
                'corrections',
            ]),
        ]);
    }

    public function correction(
        StoreReviewCorrectionRequest $request,
        string $id,
        CreateAuthoritativeCorrection $createAuthoritativeCorrection,
    ): JsonResponse {
        $review = Review::query()
            ->with([
                'document',
                'extraction',
            ])
            ->whereKey($id)
            ->where(
                'reviewer_id',
                $request->user()->getKey()
            )
            ->first();

        if ($review === null) {
            throw (new ModelNotFoundException)->setModel(
                Review::class,
                [$id],
            );
        }

        $evidence = $createAuthoritativeCorrection->execute(
            $review,
            $request->string('field_path')->toString(),
            $request->input('value'),
        );

        return response()->json([
            'data' => [
                'review' => $review->fresh([
                    'document',
                    'extraction',
                    'corrections',
                ]),
                'evidence' => $evidence->fresh(),
            ],
        ]);
    }
}
