<?php

namespace App\Http\Controllers\Api;

use App\Application\Audit\AuditLogger;
use App\Application\Documents\DuplicateDocumentException;
use App\Application\Documents\GoogleDriveClient;
use App\Application\Documents\UploadDocument;
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

final class GoogleDriveController extends Controller
{
    private const STATE = 'google_drive.oauth.state';
    private const RETURN = 'google_drive.oauth.return_url';
    private const USER = 'google_drive.oauth.user_id';

    public function redirect(Request $request, GoogleDriveClient $client): RedirectResponse
    {
        $state = Str::random(64);
        $returnUrl = $this->safeReturnUrl($request->query('returnUrl'));
        $request->session()->put([self::STATE => $state, self::RETURN => $returnUrl, self::USER => $request->user()->getKey()]);

        try {
            $setting = $client->settingForUser($request->user());
            if (! $client->isConfigured($setting)) throw new RuntimeException('Google Drive OAuth is not configured.');
            return redirect()->away($client->authorizationUrl($setting, $state));
        } catch (RuntimeException) {
            return redirect($returnUrl.'?storageError=unavailable');
        }
    }

    public function callback(Request $request, GoogleDriveClient $client, AuditLogger $audit): RedirectResponse
    {
        $returnUrl = $request->session()->pull(self::RETURN, '/settings');
        $expectedState = $request->session()->pull(self::STATE);
        $userId = $request->session()->pull(self::USER);
        $user = is_string($userId) ? User::query()->find($userId) : null;
        if (! is_string($expectedState) || ! hash_equals($expectedState, (string) $request->query('state')) || $user === null) return redirect($returnUrl.'?storageError=state');
        if ($request->query('error') || ! is_string($request->query('code'))) return redirect($returnUrl.'?storageError=cancelled');

        try {
            $setting = $client->settingForUser($user);
            if (! $client->isConfigured($setting)) throw new RuntimeException('Google Drive OAuth is not configured.');
            $tokens = $client->exchangeCode($setting, $request->query('code'));
            $profile = $client->getProfile((string) ($tokens['access_token'] ?? ''));
            $accountId = (string) ($profile['sub'] ?? '');
            if ($accountId === '') throw new RuntimeException('Google did not return a usable account.');
            $connection = StorageConnection::query()->firstOrNew(['user_id' => $user->getKey(), 'provider' => 'google_drive', 'external_account_id' => $accountId]);
            $connection->display_name = (string) ($profile['name'] ?? $profile['email'] ?? 'Google Drive');
            $connection->status = 'connected';
            $connection->metadata = ['profile_name' => $profile['name'] ?? null, 'profile_email' => $profile['email'] ?? null];
            $connection->connected_at ??= now();
            $connection->last_error = null;
            $connection->save();
            $client->saveTokens($connection, $tokens);
            $audit->record($user, 'storage_connection.connected', 'storage_connection', $connection->getKey(), ['provider' => 'google_drive', 'external_account_id' => $accountId]);
            return redirect($returnUrl.'?storageConnected=google_drive');
        } catch (\Throwable $exception) {
            Log::warning('Google Drive OAuth callback failed.', ['exception' => $exception]);
            return redirect($returnUrl.'?storageError=oauth');
        }
    }

    public function items(Request $request, string $id, GoogleDriveClient $client): JsonResponse
    {
        $connection = $this->connection($request, $id);
        $parent = (string) $request->query('parent', 'root');
        $query = "'".addcslashes($parent, "\\'")."' in parents and trashed = false";
        $response = $client->get($connection, '/files', ['q' => $query, 'orderBy' => 'folder,name', 'pageSize' => 100, 'fields' => 'files(id,name,size,mimeType,webViewLink,modifiedTime,version,parents)']);
        $items = array_map(static fn (array $item): array => [
            'id' => $item['id'] ?? '', 'name' => $item['name'] ?? '', 'size' => (int) ($item['size'] ?? 0),
            'file' => ($item['mimeType'] ?? '') !== 'application/vnd.google-apps.folder' ? ['mimeType' => $item['mimeType'] ?? null] : null,
            'folder' => ($item['mimeType'] ?? '') === 'application/vnd.google-apps.folder' ? ['childCount' => 0] : null,
            'webUrl' => $item['webViewLink'] ?? null, 'eTag' => $item['version'] ?? null,
        ], $response['files'] ?? []);
        return response()->json(['data' => $items]);
    }

    public function import(Request $request, string $id, GoogleDriveClient $client, UploadDocument $uploadDocument): JsonResponse
    {
        $data = $request->validate(['item_id' => ['required', 'string', 'max:1024'], 'financial_year_id' => ['nullable', 'uuid'], 'document_type_id' => ['required', 'uuid']]);
        $connection = $this->connection($request, $id);
        $item = $client->get($connection, '/files/'.rawurlencode($data['item_id']), ['fields' => 'id,name,size,mimeType,webViewLink,version']);
        if (($item['mimeType'] ?? '') === 'application/vnd.google-apps.folder') return response()->json(['message' => 'Only files can be imported.'], 422);
        if (! in_array($item['mimeType'] ?? '', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/tiff'], true)) return response()->json(['message' => 'Only PDF and image files can be imported.'], 422);
        if ((int) ($item['size'] ?? 0) > 20 * 1024 * 1024) return response()->json(['message' => 'Google Drive files must be 20 MB or smaller.'], 422);
        $tempPath = tempnam(sys_get_temp_dir(), 'taxvault-google-drive-');
        if ($tempPath === false) return response()->json(['message' => 'Unable to prepare the Google Drive import.'], 500);
        try {
            file_put_contents($tempPath, $client->download($connection, $data['item_id'])->body());
            $file = new UploadedFile($tempPath, (string) ($item['name'] ?? 'google-drive-document'), (string) ($item['mimeType'] ?? 'application/octet-stream'), UPLOAD_ERR_OK, true);
            $document = $uploadDocument->execute(user: $request->user(), financialYearId: $data['financial_year_id'] ?? null, documentTypeId: $data['document_type_id'], file: $file, provenance: ['source' => 'google_drive_import', 'provider' => 'google_drive', 'connection_id' => $connection->getKey(), 'external_account_id' => $connection->external_account_id, 'external_item_id' => $data['item_id'], 'external_url' => $item['webViewLink'] ?? null, 'external_version' => $item['version'] ?? null], storageProvider: 'google_drive', syncStatus: 'synced', externalAccountId: $connection->external_account_id, externalItemId: $data['item_id'], externalUrl: $item['webViewLink'] ?? null, externalVersion: $item['version'] ?? null, lastSyncedAt: now()->toISOString());
            return response()->json(['data' => $document->fresh('latestExtraction')], 201);
        } catch (DuplicateDocumentException $exception) {
            $existing = $exception->existingDocument;
            return response()->json(['code' => 'duplicate_document', 'message' => 'This file was already imported or uploaded to your account.', 'duplicate_document' => ['id' => $existing->getKey(), 'original_filename' => $existing->original_filename, 'status' => $existing->status]], 409);
        } finally { @unlink($tempPath); }
    }

    private function connection(Request $request, string $id): StorageConnection
    {
        return StorageConnection::query()->where('user_id', $request->user()->getKey())->where('provider', 'google_drive')->whereKey($id)->firstOrFail();
    }

    private function safeReturnUrl(mixed $returnUrl): string
    {
        return is_string($returnUrl) && str_starts_with($returnUrl, '/') && ! str_starts_with($returnUrl, '//') ? $returnUrl : '/settings';
    }
}
