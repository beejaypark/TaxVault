<?php

namespace App\Application\Documents;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Jobs\ProcessUploadedDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final class UploadDocument
{
    public function __construct(
        private readonly CreateDocument $createDocument,
    ) {}

    public function execute(
        User $user,
        ?string $financialYearId,
        string $documentTypeId,
        UploadedFile $file,
        array $provenance = ['source' => 'web_upload'],
    ): Document {
        $extension = strtolower(
            $file->guessExtension() ?: $file->getClientOriginalExtension()
        );

        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'], true)) {
            throw new LogicException('Unsupported document file extension.');
        }

        $sha256 = hash_file('sha256', $file->getRealPath());

        if (! is_string($sha256)) {
            throw new LogicException('Unable to calculate the document SHA-256 hash.');
        }

        $disk = 'documents';
        $storedPath = null;

        try {
            $document = DB::transaction(function () use (
                $user,
                $financialYearId,
                $documentTypeId,
                $file,
                $extension,
                $sha256,
                $disk,
                $provenance,
                &$storedPath,
            ): Document {
                User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $duplicate = Document::query()
                    ->with('financialYear:id,year_code')
                    ->where('user_id', $user->getKey())
                    ->where('content_sha256', $sha256)
                    ->orderBy('uploaded_at')
                    ->first();

                if ($duplicate !== null) {
                    throw new DuplicateDocumentException($duplicate);
                }

                $financialYear = null;
                if ($financialYearId !== null) {
                    $financialYear = FinancialYear::query()
                        ->whereKey($financialYearId)
                        ->where('user_id', $user->getKey())
                        ->where('status', 'active')
                        ->first();

                    if ($financialYear === null) {
                        throw new LogicException(
                            'Financial year does not exist, is inactive, or does not belong to the authenticated user.'
                        );
                    }
                }

                $documentTypeExists = DocumentType::query()
                    ->whereKey($documentTypeId)
                    ->where('status', 'active')
                    ->exists();

                if (! $documentTypeExists) {
                    throw new LogicException(
                        'Document type does not exist or is inactive.'
                    );
                }

                $storedPath = Storage::disk($disk)->putFileAs(
                    $financialYear?->year_code ?? 'unassigned',
                    $file,
                    Str::uuid().'.'.$extension,
                );

                if ($storedPath === false) {
                    throw new LogicException('Unable to store the uploaded document.');
                }

                return $this->createDocument->execute(
                    user: $user,
                    financialYearId: $financialYearId,
                    documentTypeId: $documentTypeId,
                    storageDisk: $disk,
                    objectKey: $storedPath,
                    contentSha256: $sha256,
                    originalFilename: $file->getClientOriginalName(),
                    mimeType: $file->getMimeType(),
                    sizeBytes: $file->getSize(),
                    uploadedAt: now()->toISOString(),
                    provenance: $provenance,
                    status: 'processing',
                );
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk($disk)->delete($storedPath);
            }

            throw $exception;
        }

        ProcessUploadedDocument::dispatch($document->getKey())->afterCommit();

        return $document;
    }
}
