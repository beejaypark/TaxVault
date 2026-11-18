<?php

namespace App\Http\Controllers\Api;

use App\Application\Audit\AuditLogger;
use App\Application\Documents\OneDriveClient;
use App\Application\Documents\UploadDocument;
use App\Application\Documents\DuplicateDocumentException;
use App\Domain\Documents\Models\StorageConnection;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class OneDriveController extends Controller
{
    private const STATE_SESSION_KEY = 'onedrive.oauth.state';
    private const RETURN_SESSION_KEY = 'onedrive.oauth.return_url';
    private const USER_SESSION_KEY = 'onedrive.oauth.user_id';

    public function redirect(Request $request, OneDriveClient $client): RedirectResponse
    {
        $setting = $client->settingForUser($request->user());
        $state = Str::random(64);
        $request->session()->put([
            self::STATE_SESSION_KEY => $state,
            self::RETURN_SESSION_KEY => $this->safeReturnUrl($request->query('returnUrl')),
            self::USER_SESSION_KEY => $request->user()->getKey(),
        ]);

        try {
            if (! $client->isConfigured($setting)) {
                throw new RuntimeException('Microsoft Graph OAuth is not configured for this user.');
            }
            return redirect()->away($client->authorizationUrl($setting, $state));
        } catch (RuntimeException $exception) {
            return redirect($this->safeReturnUrl($request->query('returnUrl')).'?storageError=unavailable');
        }
    }

    public function callback(Request $request, OneDriveClient $client, AuditLogger $audit): RedirectResponse
    {
        $returnUrl = $request->session()->pull(self::RETURN_SESSION_KEY, '/settings');
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $userId = $request->session()->pull(self::USER_SESSION_KEY);
        $user = is_string($userId) ? User::query()->find($userId) : null;

        if (
            ! is_string($expectedState)
            || ! is_string($request->query('state'))
            || ! hash_equals($expectedState, (string) $request->query('state'))
            || $user === null
        ) {
            return redirect($returnUrl.'?storageError=state');
        }

        if ($request->query('error') || ! is_string($request->query('code'))) {
            return redirect($returnUrl.'?storageError=cancelled');
        }

        try {
            $setting = $client->settingForUser($user);
            if (! $client->isConfigured($setting)) {
                throw new RuntimeException('Microsoft Graph OAuth is not configured for this user.');
            }
            $tokens = $client->exchangeCode($setting, $request->query('code'));
            $profile = $client->getProfile((string) ($tokens['access_token'] ?? ''));
            $drive = $client->getDrive((string) ($tokens['access_token'] ?? ''));
            $accountId = (string) ($profile['id'] ?? '');

            if ($accountId === '' || ! is_string($drive['id'] ?? null)) {
                throw new RuntimeException('Microsoft Graph did not return a usable OneDrive account.');
            }

            $connection = StorageConnection::query()->firstOrNew([
                'user_id' => $user->getKey(),
                'provider' => 'onedrive',
                'external_account_id' => $accountId,
            ]);
            $connection->display_name = (string) ($drive['name'] ?? $profile['displayName'] ?? 'OneDrive');
            $connection->status = 'connected';
            $connection->metadata = [
                'drive_id' => $drive['id'],
                'drive_type' => $drive['driveType'] ?? null,
                'web_url' => $drive['webUrl'] ?? null,
                'profile_name' => $profile['displayName'] ?? null,
                'profile_email' => $profile['mail'] ?? $profile['userPrincipalName'] ?? null,
            ];
            $connection->connected_at ??= now();
            $connection->last_error = null;
            $connection->save();
            $client->saveTokens($connection, $tokens);

            $audit->record($user, 'storage_connection.connected', 'storage_connection', $connection->getKey(), [
                'provider' => 'onedrive',
                'external_account_id' => $accountId,
            ]);

            return redirect($returnUrl.'?storageConnected=onedrive');
        } catch (\Throwable $exception) {
            Log::warning('OneDrive OAuth callback failed.', ['exception' => $exception]);

            return redirect($returnUrl.'?storageError=oauth');
        }
    }

    public function items(Request $request, string $id, OneDriveClient $client): JsonResponse
    {
        $connection = StorageConnection::query()
            ->where('user_id', $request->user()->getKey())
            ->where('provider', 'onedrive')
            ->whereKey($id)
            ->firstOrFail();

        $parent = (string) $request->query('parent', 'root');
        $path = $parent === 'root'
            ? '/me/drive/root/children'
            : '/me/drive/items/'.rawurlencode($parent).'/children';
        $response = $client->get($connection, $path.'?$select=id,name,size,file,folder,webUrl,eTag,lastModifiedDateTime,parentReference');

        return response()->json([
            'data' => $response['value'] ?? [],
            'next' => $response['@odata.nextLink'] ?? null,
        ]);
    }

    public function import(
        Request $request,
        string $id,
        OneDriveClient $client,
        UploadDocument $uploadDocument,
    ): JsonResponse {
        $data = $request->validate([
            'item_id' => ['required', 'string', 'max:1024'],
            'financial_year_id' => ['nullable', 'uuid'],
            'document_type_id' => ['required', 'uuid'],
        ]);

        $connection = StorageConnection::query()
            ->where('user_id', $request->user()->getKey())
            ->where('provider', 'onedrive')
            ->whereKey($id)
            ->firstOrFail();

        $itemId = $data['item_id'];
        $item = $client->get($connection, '/me/drive/items/'.rawurlencode($itemId).'?'.http_build_query([
            '$select' => 'id,name,size,file,webUrl,eTag,lastModifiedDateTime,parentReference',
        ]));

        if (isset($item['folder']) || ! isset($item['file'])) {
            return response()->json(['message' => 'Only files can be imported.'], 422);
        }

        $size = (int) ($item['size'] ?? 0);
        if ($size > 20 * 1024 * 1024) {
            return response()->json(['message' => 'OneDrive files must be 20 MB or smaller.'], 422);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'taxvault-onedrive-');
        if ($tempPath === false) {
            return response()->json(['message' => 'Unable to prepare the OneDrive import.'], 500);
        }

        try {
            file_put_contents($tempPath, $client->download($connection, $itemId)->body());
            $filename = is_string($item['name'] ?? null) && $item['name'] !== ''
                ? $item['name']
                : 'onedrive-document';
            $mimeType = is_string($item['file']['mimeType'] ?? null)
                ? $item['file']['mimeType']
                : null;
            $file = new UploadedFile($tempPath, $filename, $mimeType, UPLOAD_ERR_OK, true);

            $document = $uploadDocument->execute(
                user: $request->user(),
                financialYearId: $data['financial_year_id'] ?? null,
                documentTypeId: $data['document_type_id'],
                file: $file,
                provenance: [
                    'source' => 'onedrive_import',
                    'provider' => 'onedrive',
                    'connection_id' => $connection->getKey(),
                    'external_account_id' => $connection->external_account_id,
                    'external_drive_id' => $connection->metadata['drive_id'] ?? null,
                    'external_item_id' => $itemId,
                    'external_url' => $item['webUrl'] ?? null,
                    'external_version' => $item['eTag'] ?? null,
                ],
                storageProvider: 'onedrive',
                syncStatus: 'synced',
                externalAccountId: $connection->external_account_id,
                externalDriveId: $connection->metadata['drive_id'] ?? null,
                externalItemId: $itemId,
                externalUrl: $item['webUrl'] ?? null,
                externalVersion: $item['eTag'] ?? null,
                lastSyncedAt: now()->toISOString(),
            );

            return response()->json(['data' => $document->fresh('latestExtraction')], 201);
        } catch (DuplicateDocumentException $exception) {
            $existing = $exception->existingDocument;

            return response()->json([
                'code' => 'duplicate_document',
                'message' => 'This file was already imported or uploaded to your account.',
                'duplicate_document' => [
                    'id' => $existing->getKey(),
                    'original_filename' => $existing->original_filename,
                    'status' => $existing->status,
                ],
            ], 409);
        } finally {
            @unlink($tempPath);
        }
    }

    private function safeReturnUrl(mixed $returnUrl): string
    {
        return is_string($returnUrl) && str_starts_with($returnUrl, '/') && ! str_starts_with($returnUrl, '//')
            ? $returnUrl
            : '/settings';
    }
}
