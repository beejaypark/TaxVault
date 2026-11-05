<?php

namespace App\Domain\Banking;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BasiqClient
{
    public function enabled(): bool
    {
        return filled(config('services.basiq.api_key'));
    }

    public function createUser(string $email): string
    {
        $response = $this->api($this->token('SERVER_ACCESS'))->post('/users', ['email' => $email]);

        return $this->body($response)['id'] ?? throw new RuntimeException('Bank provider did not return a user.');
    }

    public function consentUrl(string $providerUserId): string
    {
        $token = $this->token('CLIENT_ACCESS', $providerUserId);

        return config('services.basiq.consent_url').'?'.http_build_query(['token' => $token]);
    }

    /**
     * Posted (non-pending) transactions on or after $since.
     *
     * @return list<array{id: string, date: string, description: string, amount: float}>
     */
    public function transactions(string $providerUserId, string $since): array
    {
        $api = $this->api($this->token('SERVER_ACCESS'));
        $path = "/users/{$providerUserId}/transactions";
        $query = ['filter' => "transaction.postDate.gteq('{$since}')", 'limit' => 500];
        $rows = [];

        for ($page = 0; $page < 20 && $path !== null; $page++) {
            $body = $this->body($api->get($path, $query));
            $query = [];

            foreach ($body['data'] ?? [] as $item) {
                if (($item['status'] ?? 'posted') !== 'posted' || empty($item['id']) || empty($item['postDate'])) {
                    continue;
                }

                $amount = abs((float) ($item['amount'] ?? 0));

                if ($amount === 0.0) {
                    continue;
                }

                $rows[] = [
                    'id' => (string) $item['id'],
                    'date' => substr((string) $item['postDate'], 0, 10),
                    'description' => trim((string) ($item['description'] ?? '')) ?: 'Bank transaction',
                    'amount' => ($item['direction'] ?? '') === 'debit' ? -$amount : $amount,
                ];
            }

            $next = $body['links']['next'] ?? null;
            $path = $next ? $this->relative($next) : null;
        }

        return $rows;
    }

    private function token(string $scope, ?string $userId = null): string
    {
        $form = ['scope' => $scope] + ($userId ? ['userId' => $userId] : []);

        $response = Http::baseUrl(config('services.basiq.base_url'))
            ->withHeaders(['Authorization' => 'Basic '.config('services.basiq.api_key'), 'basiq-version' => '3.0'])
            ->asForm()
            ->acceptJson()
            ->timeout(20)
            ->post('/token', $form);

        return $this->body($response)['access_token'] ?? throw new RuntimeException('Bank provider did not return a token.');
    }

    private function api(string $token): PendingRequest
    {
        return Http::baseUrl(config('services.basiq.base_url'))
            ->withToken($token)
            ->acceptJson()
            ->timeout(30);
    }

    private function body($response): array
    {
        if ($response->failed()) {
            throw new RuntimeException('Bank provider request failed ('.$response->status().').');
        }

        return $response->json() ?? [];
    }

    private function relative(string $url): string
    {
        $base = rtrim((string) config('services.basiq.base_url'), '/');

        return str_starts_with($url, $base) ? substr($url, strlen($base)) : $url;
    }
}
