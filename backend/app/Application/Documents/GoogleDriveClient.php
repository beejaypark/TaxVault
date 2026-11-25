<?php

namespace App\Application\Documents;

use App\Domain\Documents\Models\StorageConnection;
use App\Domain\Documents\Models\StorageProviderSetting;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleDriveClient
{
    public function settingForUser(User $user): ?StorageProviderSetting
    {
        return StorageProviderSetting::query()->where('user_id', $user->getKey())->where('provider', 'google_drive')->first();
    }

    public function isConfigured(?StorageProviderSetting $setting): bool
    {
        return $setting !== null && (string) $setting->client_id !== '' && (string) $setting->client_secret !== '';
    }

    public function authorizationUrl(StorageProviderSetting $setting, string $state): string
    {
        if (! $this->isConfigured($setting)) {
            throw new RuntimeException('Google Drive OAuth is not configured.');
        }

        return (string) config('services.google_drive.authorize_url').'?' . http_build_query([
            'client_id' => $setting->client_id,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent select_account',
            'scope' => implode(' ', config('services.google_drive.scopes', [])),
            'state' => $state,
        ]);
    }

    /** @return array<string, mixed> */
    public function exchangeCode(StorageProviderSetting $setting, string $code): array
    {
        return Http::asForm()->post((string) config('services.google_drive.token_url'), [
            'client_id' => $setting->client_id,
            'client_secret' => $setting->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function refresh(StorageConnection $connection): array
    {
        $setting = $this->settingForUser($connection->user);
        if (! $this->isConfigured($setting) || ! $connection->refresh_token) {
            throw new RuntimeException('Google Drive connection cannot be refreshed.');
        }

        return Http::asForm()->post((string) config('services.google_drive.token_url'), [
            'client_id' => $setting->client_id,
            'client_secret' => $setting->client_secret,
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function get(StorageConnection $connection, string $path, array $query = []): array
    {
        $response = $this->request($this->validAccessToken($connection))->get(ltrim($path, '/'), $query);
        if ($response->status() === 401 && $connection->refresh_token) {
            $tokens = $this->refresh($connection);
            $this->saveTokens($connection, $tokens);
            $response = $this->request((string) $tokens['access_token'])->get(ltrim($path, '/'), $query);
        }

        return $response->throw()->json();
    }

    public function download(StorageConnection $connection, string $itemId): Response
    {
        $response = $this->request($this->validAccessToken($connection))->get('/files/'.rawurlencode($itemId), ['alt' => 'media']);
        if ($response->status() === 401 && $connection->refresh_token) {
            $tokens = $this->refresh($connection);
            $this->saveTokens($connection, $tokens);
            $response = $this->request((string) $tokens['access_token'])->get('/files/'.rawurlencode($itemId), ['alt' => 'media']);
        }

        return $response->throw();
    }

    /** @return array<string, mixed> */
    public function getProfile(string $accessToken): array
    {
        return Http::withToken($accessToken)->get((string) config('services.google_drive.userinfo_url'))->throw()->json();
    }

    /** @param array<string, mixed> $tokens */
    public function saveTokens(StorageConnection $connection, array $tokens): void
    {
        $connection->access_token = (string) ($tokens['access_token'] ?? '');
        if (isset($tokens['refresh_token'])) $connection->refresh_token = (string) $tokens['refresh_token'];
        $connection->token_expires_at = now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600) - 60));
        $connection->scopes = is_string($tokens['scope'] ?? null) ? $tokens['scope'] : implode(' ', config('services.google_drive.scopes', []));
        $connection->save();
    }

    private function validAccessToken(StorageConnection $connection): string
    {
        if (! is_string($connection->access_token) || $connection->access_token === '') throw new RuntimeException('Google Drive connection has no access token.');
        return $connection->access_token;
    }

    private function request(string $accessToken): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.google_drive.api_url'), '/'))->acceptJson()->withToken($accessToken);
    }

    private function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/document-storage/google-drive/callback';
    }
}
