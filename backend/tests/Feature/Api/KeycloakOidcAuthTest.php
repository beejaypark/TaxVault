<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Providers\KeycloakProvider;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class KeycloakOidcAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.keycloak.base_url' => 'http://keycloak:8080/auth',
            'services.keycloak.public_url' => 'http://localhost:8080/auth',
            'services.keycloak.realms' => 'taxvault',
            'services.keycloak.client_id' => 'taxvault-web',
        ]);

        $csrfToken = 'keycloak-oidc-test-token';
        $this->withSession(['_token' => $csrfToken]);
        $this->withHeader('X-CSRF-TOKEN', $csrfToken);
    }

    public function test_valid_keycloak_callback_creates_a_user_and_authenticates_the_session(): void
    {
        $nonce = 'valid-oidc-nonce';
        $subject = 'keycloak-subject-123';
        [$idToken, $jwks] = $this->createIdToken($nonce, $subject);
        $this->fakeJwks($jwks);
        $this->mockKeycloakProvider($idToken, $subject);
        $this->withSession([
            'keycloak.oidc.nonce' => $nonce,
            'keycloak.oidc.return_url' => '/documents',
        ]);

        $response = $this->get('/api/auth/oidc/callback?code=authorization-code');

        $response->assertRedirectContains('/documents');
        $user = User::query()
            ->where('identity_provider', 'keycloak')
            ->where('provider_subject', $subject)
            ->firstOrFail();

        $this->assertSame('admin@example.test', $user->email);
        $this->assertSame('TaxVault Admin', $user->display_name);
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $storedIdToken = $this->app['session.store']->get('keycloak.oidc.id_token');
        $this->assertIsString($storedIdToken);
        $this->assertNotSame($idToken, $storedIdToken);
        $this->assertSame($idToken, Crypt::decryptString($storedIdToken));

        $this->withHeader('X-CSRF-TOKEN', $this->app['session.store']->token());
        $logoutResponse = $this->postJson('/api/auth/logout');
        $logoutResponse->assertOk()->assertJsonPath('message', 'Logged out.');
        $logoutUrl = $logoutResponse->json('data.logout_url');
        $this->assertIsString($logoutUrl);
        parse_str((string) parse_url($logoutUrl, PHP_URL_QUERY), $logoutParameters);
        $this->assertSame($idToken, $logoutParameters['id_token_hint']);
        $this->assertSame(
            'http://localhost:8080/login',
            $logoutParameters['post_logout_redirect_uri'],
        );
        $this->assertGuest('web');
    }

    public function test_keycloak_callback_rejects_an_id_token_with_the_wrong_nonce(): void
    {
        [$idToken, $jwks] = $this->createIdToken('different-nonce', 'keycloak-subject-456');
        $this->fakeJwks($jwks);
        $this->mockKeycloakProvider($idToken, 'keycloak-subject-456');
        $this->withSession([
            'keycloak.oidc.nonce' => 'expected-nonce',
            'keycloak.oidc.return_url' => '/dashboard',
        ]);

        $this->get('/api/auth/oidc/callback?code=authorization-code')
            ->assertRedirect('/login?authError=oidc');

        $this->assertGuest('web');
        $this->assertDatabaseMissing('users', [
            'identity_provider' => 'keycloak',
            'provider_subject' => 'keycloak-subject-456',
        ]);
    }

    /**
     * @return array{string, array{keys: list<array<string, string>>}}
     */
    private function createIdToken(string $nonce, string $subject): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        $keyDetails = openssl_pkey_get_details($key);
        $this->assertIsArray($keyDetails);

        $idToken = JWT::encode([
            'iss' => 'http://localhost:8080/auth/realms/taxvault',
            'aud' => 'taxvault-web',
            'sub' => $subject,
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => $nonce,
        ], $privateKey, 'RS256', 'test-key');

        $rsaDetails = $keyDetails['rsa'];

        return [
            $idToken,
            [
                'keys' => [[
                    'kty' => 'RSA',
                    'kid' => 'test-key',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => $this->base64UrlEncode($rsaDetails['n']),
                    'e' => $this->base64UrlEncode($rsaDetails['e']),
                ]],
            ],
        ];
    }

    /**
     * @param array{keys: list<array<string, string>>} $jwks
     */
    private function fakeJwks(array $jwks): void
    {
        Http::fake([
            'http://keycloak:8080/auth/realms/taxvault/protocol/openid-connect/certs'
                => Http::response($jwks),
        ]);
    }

    private function mockKeycloakProvider(string $idToken, string $subject): void
    {
        $oauthUser = (new SocialiteUser())->setRaw([
            'sub' => $subject,
            'email' => 'admin@example.test',
            'name' => 'TaxVault Admin',
        ])->map([
            'id' => $subject,
            'email' => 'admin@example.test',
            'name' => 'TaxVault Admin',
        ]);

        $provider = Mockery::mock(KeycloakProvider::class)->makePartial();
        $provider->shouldReceive('enablePKCE')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturnUsing(
            function () use ($idToken, $oauthUser): SocialiteUser {
                request()->session()->put(
                    'keycloak.oidc.pending_id_token',
                    $idToken,
                );

                return $oauthUser;
            },
        );

        Socialite::shouldReceive('driver')
            ->once()
            ->with('keycloak')
            ->andReturn($provider);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
