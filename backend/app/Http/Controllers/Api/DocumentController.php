<?php

namespace App\Http\Controllers\Api;

use App\Application\Documents\AssignDocumentFinancialYear;
use App\Application\Documents\ConfirmDocumentPayment;
use App\Application\Documents\DeleteDocument;
use App\Application\Documents\DocumentDeletionConflict;
use App\Application\Documents\DuplicateDocumentException;
use App\Application\Documents\FinancialYearSuggestionUnavailable;
use App\Application\Documents\UploadDocument;
use App\Application\Reviews\DeleteReview;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\AssignDocumentFinancialYearRequest;
use App\Http\Requests\Documents\ConfirmDocumentPaymentRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Jobs\ProcessUploadedDocument;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->where('user_id', $request->user()->getKey())
            ->with('latestExtraction')
            ->orderByDesc('uploaded_at')
            ->get();

        return response()->json([
            'data' => $documents,
        ]);
    }

    public function store(
        StoreDocumentRequest $request,
        UploadDocument $uploadDocument,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $document = $uploadDocument->execute(
                user: $request->user(),
                financialYearId: $validated['financial_year_id'] ?? null,
                documentTypeId: $validated['document_type_id'],
                file: $request->file('file'),
            );
        } catch (DuplicateDocumentException $exception) {
            $existing = $exception->existingDocument;

            return response()->json([
                'code' => 'duplicate_document',
                'message' => 'This file was already uploaded to your account.',
                'duplicate_document' => [
                    'id' => $existing->getKey(),
                    'original_filename' => $existing->original_filename,
                    'financial_year_code' => $existing->financialYear?->year_code,
                    'uploaded_at' => $existing->uploaded_at,
                    'status' => $existing->status,
                ],
            ], 409);
        }

        return response()->json([
            'data' => $document,
        ], 201);
    }

    public function show(
        Request $request,
        string $id,
    ): JsonResponse {
        $document = Document::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->with('latestExtraction')
            ->first();

        if ($document === null) {
            throw (new ModelNotFoundException)->setModel(
                Document::class,
                [$id],
            );
        }

        return response()->json([
            'data' => $document,
        ]);
    }

    public function assignFinancialYear(
        AssignDocumentFinancialYearRequest $request,
        string $id,
        AssignDocumentFinancialYear $assignFinancialYear,
    ): JsonResponse {
        try {
            $validated = $request->validated();
            $document = $assignFinancialYear->execute(
                user: $request->user(),
                documentId: $id,
                confirmedYearCode: $validated['financial_year_code'] ?? null,
                financialYearId: $validated['financial_year_id'] ?? null,
            );
        } catch (FinancialYearSuggestionUnavailable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $document,
        ]);
    }

    public function content(Request $request, string $id)
    {
        $document = Document::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($document === null) {
            throw (new ModelNotFoundException)->setModel(Document::class, [$id]);
        }

        return response()->streamDownload(
            static function () use ($document): void {
                echo Storage::disk($document->storage_disk)->get($document->object_key);
            },
            $document->original_filename ?? 'tax-document',
            [
                'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $request->boolean('inline') ? 'inline' : 'attachment',
        );
    }

    public function destroy(
        Request $request,
        string $id,
        DeleteDocument $deleteDocument,
        DeleteReview $deleteReview,
    ): JsonResponse {
        try {
            $deleteDocument->execute($request->user(), $id, $deleteReview);
        } catch (DocumentDeletionConflict $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'Document permanently deleted.',
        ]);
    }

    public function confirmPayment(
        ConfirmDocumentPaymentRequest $request,
        string $id,
        ConfirmDocumentPayment $confirmDocumentPayment,
    ): JsonResponse {
        $validated = $request->validated();

        $document = $confirmDocumentPayment->execute(
            user: $request->user(),
            documentId: $id,
            paymentDate: $validated['payment_date'],
            paymentMethod: $validated['payment_method'],
            amount: (string) $validated['amount'],
        );

        return response()->json([
            'data' => $document,
        ]);
    }

    public function retry(Request $request, string $id): JsonResponse
    {
        $document = Document::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($document === null) {
            throw (new ModelNotFoundException)->setModel(Document::class, [$id]);
        }

        $isLegacyUnprocessedDocument = $document->status === 'active'
            && ! $document->latestExtraction()->exists();

        if ($document->status !== 'processing_failed' && ! $isLegacyUnprocessedDocument) {
            return response()->json([
                'message' => 'Only failed or unprocessed documents can be processed again.',
            ], 409);
        }

        $document->update(['status' => 'processing']);
        ProcessUploadedDocument::dispatch($document->getKey())->afterCommit();

        return response()->json(['data' => $document->fresh('latestExtraction')], 202);
    }
}
