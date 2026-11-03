<?php

namespace App\Application\Documents;

use App\Application\Reviews\DeleteReview;
use App\Domain\Documents\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DeleteDocument
{
    public function execute(
        User $user,
        string $documentId,
        DeleteReview $deleteReview,
    ): void {
        DB::transaction(function () use ($user, $documentId, $deleteReview): void {
            $document = Document::query()
                ->whereKey($documentId)
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                throw (new ModelNotFoundException)->setModel(
                    Document::class,
                    [$documentId],
                );
            }

            if ($document->status === 'processing') {
                throw new DocumentDeletionConflict(
                    'This document cannot be deleted while text extraction is in progress. Try again when processing finishes.'
                );
            }

            $deleteReview->deleteForDocument($document);

            DB::table('document_extractions')
                ->where('document_id', $documentId)
                ->update([
                    'source_document_id' => $documentId,
                    'source_financial_year_id' => $document->financial_year_id,
                    'document_id' => null,
                ]);

            DB::table('transactions')
                ->where('document_id', $documentId)
                ->update([
                    'source_document_id' => $documentId,
                    'document_id' => null,
                ]);

            DB::table('financial_record_documents')
                ->where('document_id', $documentId)
                ->update([
                    'source_document_id' => $documentId,
                    'document_id' => null,
                ]);

            DB::table('tax_evidences')
                ->where('document_id', $documentId)
                ->where('source_type', 'document')
                ->whereNull('source_id')
                ->update(['source_id' => $documentId]);

            $disk = Storage::disk($document->storage_disk);
            if ($disk->exists($document->object_key) && ! $disk->delete($document->object_key)) {
                throw new RuntimeException('Unable to delete the stored document file.');
            }

            $document->delete();
        });
    }
}
