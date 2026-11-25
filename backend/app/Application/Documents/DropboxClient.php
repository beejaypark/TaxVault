<?php

namespace App\Application\Documents;

use App\Domain\Documents\Models\StorageConnection;
use App\Domain\Documents\Models\StorageProviderSetting;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DropboxClient
{
    public function settingForUser(User $user): ?StorageProviderSetting
    {
        return StorageProviderSetting::query()->where('user_id', $user->getKey())->where('provider', 'dropbox')->first();
    }

    public function isConfigured(?StorageProviderSetting $setting): bool
    {
        return $setting !== null && (string) $setting->client_id !== '' && (string) $setting->client_secret !== '';
    }

    public function authorizationUrl(StorageProviderSetting $setting, string $state): string
    {
        if (! $this->isConfigured($setting)) throw new RuntimeException('Dropbox OAuth is not configured.');
        return (string) config('services.dropbox.authorize_url').'?'.http_build_query([
            'client_id' => $setting->client_id, 'redirect_uri' => $this->redirectUri(), 'response_type' => 'code',
            'token_access_type' => 'offline', 'scope' => implode(' ', config('services.dropbox.scopes', [])), 'state' => $state,
        ]);
    }

    /** @return array<string, mixed> */
    public function exchangeCode(StorageProviderSetting $setting, string $code): array
    {
        return Http::asForm()->post((string) config('services.dropbox.token_url'), [
            'code' => $code, 'grant_type' => 'authorization_code', 'client_id' => $setting->client_id, 'client_secret' => $setting->client_secret, 'redirect_uri' => $this->redirectUri(),
        ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function refresh(StorageConnection $connection): array
    {
        $setting = $this->settingForUser($connection->user);
        if (! $this->isConfigured($setting) || ! $connection->refresh_token) throw new RuntimeException('Dropbox connection cannot be refreshed.');
        return Http::asForm()->post((string) config('services.dropbox.token_url'), ['refresh_token' => $connection->refresh_token, 'grant_type' => 'refresh_token', 'client_id' => $setting->client_id, 'client_secret' => $setting->client_secret])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function rpc(StorageConnection $connection, string $endpoint, array $payload = []): array
    {
        $response = Http::withToken($this->accessToken($connection))->acceptJson()->post(rtrim((string) config('services.dropbox.api_url'), '/').$endpoint, $payload);
        if ($response->status() === 401 && $connection->refresh_token) { $tokens = $this->refresh($connection); $this->saveTokens($connection, $tokens); $response = Http::withToken((string) $tokens['access_token'])->acceptJson()->post(rtrim((string) config('services.dropbox.api_url'), '/').$endpoint, $payload); }
        return $response->throw()->json();
    }

    public function download(StorageConnection $connection, string $path): Response
    {
        $headers = ['Dropbox-API-Arg' => json_encode(['path' => $path], JSON_THROW_ON_ERROR)];
        $response = Http::withToken($this->accessToken($connection))->withHeaders($headers)->post(rtrim((string) config('services.dropbox.content_url'), '/').'/2/files/download');
        if ($response->status() === 401 && $connection->refresh_token) { $tokens = $this->refresh($connection); $this->saveTokens($connection, $tokens); $response = Http::withToken((string) $tokens['access_token'])->withHeaders($headers)->post(rtrim((string) config('services.dropbox.content_url'), '/').'/2/files/download'); }
        return $response->throw();
    }

    /** @return array<string, mixed> */
    public function account(string $accessToken): array
    {
        return Http::withToken($accessToken)->post(rtrim((string) config('services.dropbox.api_url'), '/').'/2/users/get_current_account')->throw()->json();
    }

    /** @param array<string, mixed> $tokens */
    public function saveTokens(StorageConnection $connection, array $tokens): void
    {
        $connection->access_token = (string) ($tokens['access_token'] ?? '');
        if (isset($tokens['refresh_token'])) $connection->refresh_token = (string) $tokens['refresh_token'];
        $connection->token_expires_at = now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 14400) - 60));
        $connection->scopes = implode(' ', config('services.dropbox.scopes', []));
        $connection->save();
    }

    private function accessToken(StorageConnection $connection): string
    {
        if (! is_string($connection->access_token) || $connection->access_token === '') throw new RuntimeException('Dropbox connection has no access token.');
        return $connection->access_token;
    }

    private function redirectUri(): string { return rtrim((string) config('app.url'), '/').'/api/document-storage/dropbox/callback'; }
}
