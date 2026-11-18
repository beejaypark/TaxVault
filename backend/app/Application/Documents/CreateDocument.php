<?php

namespace App\Application\Documents;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateDocument
{
    public function execute(
        User $user,
        ?string $financialYearId,
        string $documentTypeId,
        string $storageDisk,
        string $objectKey,
        string $contentSha256,
        ?string $originalFilename = null,
        ?string $mimeType = null,
        ?int $sizeBytes = null,
        ?string $capturedAt = null,
        ?string $uploadedAt = null,
        ?array $provenance = null,
        string $status = 'active',
        string $storageProvider = 'taxvault',
        string $syncStatus = 'local',
        ?string $externalAccountId = null,
        ?string $externalDriveId = null,
        ?string $externalItemId = null,
        ?string $externalUrl = null,
        ?string $externalVersion = null,
        ?string $lastSyncedAt = null,
    ): Document {
        return DB::transaction(function () use (
            $user,
            $financialYearId,
            $documentTypeId,
            $storageDisk,
            $objectKey,
            $contentSha256,
            $originalFilename,
            $mimeType,
            $sizeBytes,
            $capturedAt,
            $uploadedAt,
            $provenance,
            $status,
            $storageProvider,
            $syncStatus,
            $externalAccountId,
            $externalDriveId,
            $externalItemId,
            $externalUrl,
            $externalVersion,
            $lastSyncedAt,
        ): Document {
            $financialYearExists = $financialYearId === null
                || FinancialYear::query()
                    ->whereKey($financialYearId)
                    ->where('user_id', $user->getKey())
                    ->exists();

            if (! $financialYearExists) {
                throw new LogicException(
                    'Financial year does not belong to the authenticated user.'
                );
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

            return Document::query()->create([
                'user_id' => $user->getKey(),
                'financial_year_id' => $financialYearId,
                'document_type_id' => $documentTypeId,
                'storage_provider' => $storageProvider,
                'external_account_id' => $externalAccountId,
                'external_drive_id' => $externalDriveId,
                'external_item_id' => $externalItemId,
                'external_url' => $externalUrl,
                'external_version' => $externalVersion,
                'sync_status' => $syncStatus,
                'last_synced_at' => $lastSyncedAt,
                'storage_disk' => $storageDisk,
                'object_key' => $objectKey,
                'content_sha256' => $contentSha256,
                'original_filename' => $originalFilename,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'captured_at' => $capturedAt,
                'uploaded_at' => $uploadedAt,
                'provenance' => $provenance,
                'status' => $status,
            ]);
        });
    }
}
