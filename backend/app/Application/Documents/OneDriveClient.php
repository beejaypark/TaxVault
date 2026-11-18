<?php

namespace App\Application\Documents;

use App\Domain\Documents\Models\StorageConnection;
use App\Domain\Documents\Models\StorageProviderSetting;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use RuntimeException;

final class OneDriveClient
{
    public function settingForUser(User $user): ?StorageProviderSetting
    {
        return StorageProviderSetting::query()
            ->where('user_id', $user->getKey())
            ->where('provider', 'onedrive')
            ->first();
    }

    public function isConfigured(?StorageProviderSetting $setting): bool
    {
        return $setting !== null
            && (string) $setting->client_id !== ''
            && (string) $setting->client_secret !== '';
    }

    public function authorizationUrl(StorageProviderSetting $setting, string $state): string
    {
        $config = config('services.microsoft_graph');

        if (! $this->isConfigured($setting)) {
            throw new RuntimeException('Microsoft Graph OAuth is not configured.');
        }

        return rtrim((string) $config['authorize_url'], '/').'/'.rawurlencode((string) $setting->tenant).'/oauth2/v2.0/authorize?'.http_build_query([
            'client_id' => $setting->client_id,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'prompt' => 'select_account',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state,
        ]);
    }

    /** @return array<string, mixed> */
    public function exchangeCode(StorageProviderSetting $setting, string $code): array
    {
        $config = config('services.microsoft_graph');
        $response = Http::asForm()->post(
            rtrim((string) $config['authorize_url'], '/').'/'.rawurlencode((string) $setting->tenant).'/oauth2/v2.0/token',
            [
                'client_id' => $setting->client_id,
                'client_secret' => $setting->client_secret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'scope' => implode(' ', $config['scopes']),
            ],
        )->throw()->json();

        return $response;
    }

    /** @return array<string, mixed> */
    public function refresh(StorageConnection $connection): array
    {
        $config = config('services.microsoft_graph');
        $setting = $this->settingForUser($connection->user);
        if (! $this->isConfigured($setting)) {
            throw new RuntimeException('Microsoft Graph OAuth is not configured for this user.');
        }
        $response = Http::asForm()->post(
            rtrim((string) $config['authorize_url'], '/').'/'.rawurlencode((string) $setting->tenant).'/oauth2/v2.0/token',
            [
                'client_id' => $setting->client_id,
                'client_secret' => $setting->client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
                'scope' => implode(' ', $config['scopes']),
            ],
        )->throw()->json();

        return $response;
    }

    /** @return array<string, mixed> */
    public function get(StorageConnection $connection, string $path): array
    {
        $accessToken = $this->validAccessToken($connection);
        $response = $this->request($accessToken)->get(ltrim($path, '/'));

        if ($response->status() === 401 && $connection->refresh_token) {
            $tokens = $this->refresh($connection);
            $this->saveTokens($connection, $tokens);
            $response = $this->request((string) $tokens['access_token'])->get(ltrim($path, '/'));
        }

        return $response->throw()->json();
    }

    public function download(StorageConnection $connection, string $itemId): Response
    {
        $accessToken = $this->validAccessToken($connection);
        $response = $this->request($accessToken)->get('/me/drive/items/'.rawurlencode($itemId).'/content');

        if ($response->status() === 401 && $connection->refresh_token) {
            $tokens = $this->refresh($connection);
            $this->saveTokens($connection, $tokens);
            $response = $this->request((string) $tokens['access_token'])
                ->get('/me/drive/items/'.rawurlencode($itemId).'/content');
        }

        return $response->throw();
    }

    /** @return array<string, mixed> */
    public function getProfile(string $accessToken): array
    {
        return $this->request($accessToken)
            ->get('/me?$select=id,displayName,mail,userPrincipalName')
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    public function getDrive(string $accessToken): array
    {
        return $this->request($accessToken)
            ->get('/me/drive?$select=id,name,driveType,webUrl')
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    private function validAccessToken(StorageConnection $connection): string
    {
        if (! is_string($connection->access_token) || $connection->access_token === '') {
            throw new RuntimeException('OneDrive connection has no access token.');
        }

        return $connection->access_token;
    }

    private function request(string $accessToken): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.microsoft_graph.api_url'), '/'))
            ->acceptJson()
            ->withToken($accessToken);
    }

    /** @param array<string, mixed> $tokens */
    public function saveTokens(StorageConnection $connection, array $tokens): void
    {
        $connection->access_token = (string) ($tokens['access_token'] ?? '');
        if (isset($tokens['refresh_token'])) {
            $connection->refresh_token = (string) $tokens['refresh_token'];
        }
        $connection->token_expires_at = now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600) - 60));
        $connection->scopes = is_string($tokens['scope'] ?? null) ? $tokens['scope'] : implode(' ', config('services.microsoft_graph.scopes', []));
        $connection->save();
    }

    private function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/document-storage/onedrive/callback';
    }
}
