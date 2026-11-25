<?php

namespace App\Http\Controllers\Api;

use App\Application\Audit\AuditLogger;
use App\Application\Documents\DropboxClient;
use App\Application\Documents\DuplicateDocumentException;
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

final class DropboxController extends Controller
{
    private const STATE = 'dropbox.oauth.state'; private const RETURN = 'dropbox.oauth.return_url'; private const USER = 'dropbox.oauth.user_id';

    public function redirect(Request $request, DropboxClient $client): RedirectResponse
    {
        $state = Str::random(64); $return = $this->safeReturnUrl($request->query('returnUrl'));
        $request->session()->put([self::STATE => $state, self::RETURN => $return, self::USER => $request->user()->getKey()]);
        try { $setting = $client->settingForUser($request->user()); if (! $client->isConfigured($setting)) throw new RuntimeException(); return redirect()->away($client->authorizationUrl($setting, $state)); }
        catch (RuntimeException) { return redirect($return.'?storageError=unavailable'); }
    }

    public function callback(Request $request, DropboxClient $client, AuditLogger $audit): RedirectResponse
    {
        $return = $request->session()->pull(self::RETURN, '/settings'); $expected = $request->session()->pull(self::STATE); $userId = $request->session()->pull(self::USER); $user = is_string($userId) ? User::query()->find($userId) : null;
        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state')) || $user === null) return redirect($return.'?storageError=state');
        if ($request->query('error') || ! is_string($request->query('code'))) return redirect($return.'?storageError=cancelled');
        try {
            $setting = $client->settingForUser($user); if (! $client->isConfigured($setting)) throw new RuntimeException();
            $tokens = $client->exchangeCode($setting, $request->query('code')); $account = $client->account((string) ($tokens['access_token'] ?? '')); $accountId = (string) ($account['account_id'] ?? ''); if ($accountId === '') throw new RuntimeException('Dropbox account was not returned.');
            $connection = StorageConnection::query()->firstOrNew(['user_id' => $user->getKey(), 'provider' => 'dropbox', 'external_account_id' => $accountId]);
            $connection->display_name = (string) ($account['name']['display_name'] ?? $account['email'] ?? 'Dropbox'); $connection->status = 'connected'; $connection->metadata = ['profile_name' => $account['name']['display_name'] ?? null, 'profile_email' => $account['email'] ?? null]; $connection->connected_at ??= now(); $connection->last_error = null; $connection->save(); $client->saveTokens($connection, $tokens);
            $audit->record($user, 'storage_connection.connected', 'storage_connection', $connection->getKey(), ['provider' => 'dropbox', 'external_account_id' => $accountId]);
            return redirect($return.'?storageConnected=dropbox');
        } catch (\Throwable $exception) { Log::warning('Dropbox OAuth callback failed.', ['exception' => $exception]); return redirect($return.'?storageError=oauth'); }
    }

    public function items(Request $request, string $id, DropboxClient $client): JsonResponse
    {
        $connection = $this->connection($request, $id); $parent = (string) $request->query('parent', '');
        $response = $client->rpc($connection, '/2/files/list_folder', ['path' => $parent === 'root' ? '' : $parent, 'recursive' => false, 'include_deleted' => false, 'limit' => 2000]);
        $items = array_map(static function (array $item): array { $folder = ($item['.tag'] ?? '') === 'folder'; return ['id' => $item['path_display'] ?? $item['path_lower'] ?? '', 'name' => $item['name'] ?? '', 'size' => (int) ($item['size'] ?? 0), 'file' => $folder ? null : ['mimeType' => null], 'folder' => $folder ? ['childCount' => 0] : null, 'webUrl' => null, 'eTag' => $item['rev'] ?? null]; }, $response['entries'] ?? []);
        return response()->json(['data' => $items]);
    }

    public function import(Request $request, string $id, DropboxClient $client, UploadDocument $uploadDocument): JsonResponse
    {
        $data = $request->validate(['item_id' => ['required', 'string', 'max:2048'], 'financial_year_id' => ['nullable', 'uuid'], 'document_type_id' => ['required', 'uuid']]); $connection = $this->connection($request, $id); $metadata = $client->rpc($connection, '/2/files/get_metadata', ['path' => $data['item_id']]); $mime = $this->mimeType((string) ($metadata['name'] ?? '')); if (($metadata['.tag'] ?? '') !== 'file') return response()->json(['message' => 'Only files can be imported.'], 422); if ($mime === null) return response()->json(['message' => 'Only PDF and image files can be imported.'], 422); if ((int) ($metadata['size'] ?? 0) > 20 * 1024 * 1024) return response()->json(['message' => 'Dropbox files must be 20 MB or smaller.'], 422);
        $tempPath = tempnam(sys_get_temp_dir(), 'taxvault-dropbox-'); if ($tempPath === false) return response()->json(['message' => 'Unable to prepare the Dropbox import.'], 500);
        try { file_put_contents($tempPath, $client->download($connection, $data['item_id'])->body()); $file = new UploadedFile($tempPath, (string) ($metadata['name'] ?? 'dropbox-document'), $mime, UPLOAD_ERR_OK, true); $document = $uploadDocument->execute(user: $request->user(), financialYearId: $data['financial_year_id'] ?? null, documentTypeId: $data['document_type_id'], file: $file, provenance: ['source' => 'dropbox_import', 'provider' => 'dropbox', 'connection_id' => $connection->getKey(), 'external_account_id' => $connection->external_account_id, 'external_item_id' => $data['item_id'], 'external_version' => $metadata['rev'] ?? null], storageProvider: 'dropbox', syncStatus: 'synced', externalAccountId: $connection->external_account_id, externalItemId: $data['item_id'], externalVersion: $metadata['rev'] ?? null, lastSyncedAt: now()->toISOString()); return response()->json(['data' => $document->fresh('latestExtraction')], 201); }
        catch (DuplicateDocumentException $exception) { $existing = $exception->existingDocument; return response()->json(['code' => 'duplicate_document', 'message' => 'This file was already imported or uploaded to your account.', 'duplicate_document' => ['id' => $existing->getKey(), 'original_filename' => $existing->original_filename, 'status' => $existing->status]], 409); }
        finally { @unlink($tempPath); }
    }

    private function connection(Request $request, string $id): StorageConnection { return StorageConnection::query()->where('user_id', $request->user()->getKey())->where('provider', 'dropbox')->whereKey($id)->firstOrFail(); }
    private function mimeType(string $name): ?string { return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) { 'pdf' => 'application/pdf', 'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'tif', 'tiff' => 'image/tiff', default => null }; }
    private function safeReturnUrl(mixed $returnUrl): string { return is_string($returnUrl) && str_starts_with($returnUrl, '/') && ! str_starts_with($returnUrl, '//') ? $returnUrl : '/settings'; }
}
