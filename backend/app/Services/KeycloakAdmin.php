<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Creates and removes Keycloak accounts for invited accountants. Uses a
 * realm service-account client when configured, otherwise the master-realm
 * admin (development only).
 */
class KeycloakAdmin
{
    public function createUser(string $email, string $name, string $temporaryPassword): string
    {
        [$first, $last] = $this->splitName($name);

        $response = $this->http()->post($this->realmUrl('users'), [
            'username' => $email,
            'email' => $email,
            'firstName' => $first,
            'lastName' => $last,
            'enabled' => true,
            'emailVerified' => true,
            'credentials' => [[
                'type' => 'password',
                'value' => $temporaryPassword,
                'temporary' => true,
            ]],
        ]);

        if ($response->status() === 409) {
            throw new RuntimeException('A Keycloak account with this email already exists.');
        }

        if (! $response->created()) {
            throw new RuntimeException('Keycloak could not create the account.');
        }

        $location = (string) $response->header('Location');
        $subject = basename(parse_url($location, PHP_URL_PATH) ?: '');

        if ($subject === '') {
            throw new RuntimeException('Keycloak did not return the new account id.');
        }

        return $subject;
    }

    public function resetPassword(string $subject, string $temporaryPassword): void
    {
        $response = $this->http()->put($this->realmUrl("users/{$subject}/reset-password"), [
            'type' => 'password',
            'value' => $temporaryPassword,
            'temporary' => true,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Keycloak could not reset the password.');
        }
    }

    public function setEnabled(string $subject, bool $enabled): void
    {
        $response = $this->http()->put($this->realmUrl("users/{$subject}"), ['enabled' => $enabled]);

        if (! $response->successful()) {
            throw new RuntimeException('Keycloak could not update the account.');
        }
    }

    public function deleteUser(string $subject): void
    {
        $response = $this->http()->delete($this->realmUrl("users/{$subject}"));

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Keycloak could not delete the account.');
        }
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(15);
    }

    private function accessToken(): string
    {
        $config = config('services.keycloak');
        $base = rtrim($config['base_url'], '/');

        if (! empty($config['admin_client_id']) && ! empty($config['admin_client_secret'])) {
            $url = "{$base}/realms/{$config['realms']}/protocol/openid-connect/token";
            $form = [
                'grant_type' => 'client_credentials',
                'client_id' => $config['admin_client_id'],
                'client_secret' => $config['admin_client_secret'],
            ];
        } else {
            $url = "{$base}/realms/master/protocol/openid-connect/token";
            $form = [
                'grant_type' => 'password',
                'client_id' => 'admin-cli',
                'username' => $config['admin_user'],
                'password' => $config['admin_password'],
            ];
        }

        $response = Http::asForm()->timeout(15)->post($url, $form);
        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token)) {
            throw new RuntimeException('Keycloak admin authentication failed.');
        }

        return $token;
    }

    private function realmUrl(string $path): string
    {
        $config = config('services.keycloak');

        return rtrim($config['base_url'], '/')."/admin/realms/{$config['realms']}/{$path}";
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }
}
