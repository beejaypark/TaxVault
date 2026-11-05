<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevelopmentAdminCredential;
use App\Models\User;
use DomainException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Socialite\Facades\Socialite;
use UnexpectedValueException;

class AuthController extends Controller
{
    private const PENDING_ID_TOKEN_SESSION_KEY = 'keycloak.oidc.pending_id_token';
    private const ID_TOKEN_SESSION_KEY = 'keycloak.oidc.id_token';

    private function developmentLoginEnabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('dev_admin.enabled', false);
    }

    public function login(Request $request): JsonResponse
    {
        if (! $this->developmentLoginEnabled()) {
            return response()->json(['message' => 'Development login is disabled.'], 404);
        }

        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $credential = DevelopmentAdminCredential::query()
            ->where('login_name', strtolower(trim($credentials['login'])))
            ->where('enabled', true)
            ->first();

        if (
            ! $credential
            || ! Hash::check($credentials['password'], $credential->password_hash)
        ) {
            app(\App\Application\Audit\AuditLogger::class)->record(
                $credential?->user,
                'auth.login_failed',
                null,
                null,
                ['login' => substr(strtolower(trim($credentials['login'])), 0, 100)],
            );

            return response()->json(['message' => 'The supplied credentials are invalid.'], 422);
        }

        $user = $credential->user;

        if (! $user || $user->status !== 'active' || $user->deactivated_at !== null) {
            return response()->json(['message' => 'The supplied credentials are invalid.'], 422);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $this->present($user)]);
    }

    public function oidcRedirect(Request $request): RedirectResponse
    {
        $nonce = Str::random(64);
        $request->session()->put([
            'keycloak.oidc.nonce' => $nonce,
            'keycloak.oidc.return_url' => $this->safeReturnUrl(
                $request->query('returnUrl'),
            ),
        ]);

        $provider = Socialite::driver('keycloak');
        $provider->scopes(['openid', 'profile', 'email']);
        $provider->with(['nonce' => $nonce]);
        $provider->enablePKCE();

        $internalBaseUrl = rtrim((string) config('services.keycloak.base_url'), '/');
        $publicBaseUrl = rtrim((string) config('services.keycloak.public_url'), '/');
        $authorizationUrl = $provider->redirect()->getTargetUrl();

        if (
            $internalBaseUrl === ''
            || $publicBaseUrl === ''
            || ! str_starts_with($authorizationUrl, $internalBaseUrl.'/')
        ) {
            abort(500, 'Keycloak authorization URL is not configured correctly.');
        }

        $publicAuthorizationUrl = $publicBaseUrl.substr(
            $authorizationUrl,
            strlen($internalBaseUrl),
        );

        return redirect()->away($publicAuthorizationUrl);
    }

    public function oidcCallback(Request $request): RedirectResponse
    {
        if ($request->query('error')) {
            $this->clearOidcSession($request);

            return redirect('/login?authError=cancelled');
        }

        $nonce = $request->session()->pull('keycloak.oidc.nonce');
        $returnUrl = $this->safeReturnUrl(
            $request->session()->pull('keycloak.oidc.return_url'),
        );

        if (! is_string($nonce) || $nonce === '') {
            Log::warning('Keycloak OIDC callback did not find a session nonce.');
            $this->clearOidcSession($request);

            return redirect('/login?authError=state');
        }

        try {
            $request->session()->forget(self::PENDING_ID_TOKEN_SESSION_KEY);

            $provider = Socialite::driver('keycloak');
            $provider->enablePKCE();
            $oauthUser = $provider->user();
            $idToken = $request->session()->pull(self::PENDING_ID_TOKEN_SESSION_KEY);

            if (! is_string($idToken) || $idToken === '') {
                throw new DomainException('Keycloak did not return an ID token.');
            }

            $claims = $this->validateIdToken($idToken, $nonce);

            if (
                ! is_string($oauthUser->getId())
                || ! hash_equals($claims->sub, $oauthUser->getId())
            ) {
                throw new DomainException('Keycloak profile does not match the ID token.');
            }
        } catch (
            ConnectionException
            | DomainException
            | GuzzleException
            | InvalidArgumentException
            | RequestException
            | UnexpectedValueException $exception
        ) {
            report($exception);
            $this->clearOidcSession($request);

            return redirect('/login?authError=oidc');
        }

        $rawUser = $oauthUser->getRaw();
        $user = User::query()->firstOrNew([
            'identity_provider' => 'keycloak',
            'provider_subject' => $claims->sub,
        ]);

        if (
            $user->exists
            && ($user->status !== 'active' || $user->deactivated_at !== null)
        ) {
            $this->clearOidcSession($request);

            return redirect('/login?authError=inactive');
        }

        $user->email = is_string($rawUser['email'] ?? null)
            ? $rawUser['email']
            : null;
        $user->display_name = is_string($rawUser['name'] ?? null)
            ? $rawUser['name']
            : (is_string($rawUser['preferred_username'] ?? null)
                ? $rawUser['preferred_username']
                : $user->email);

        if (! $user->exists) {
            $user->status = 'active';
        }

        $user->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(
            self::ID_TOKEN_SESSION_KEY,
            Crypt::encryptString($idToken),
        );

        return redirect()->to($returnUrl);
    }

    public function logout(Request $request): JsonResponse
    {
        $idToken = $request->session()->get(self::ID_TOKEN_SESSION_KEY);
        $logoutUrl = is_string($idToken)
            ? $this->keycloakLogoutUrl(Crypt::decryptString($idToken))
            : null;

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out.',
            'data' => ['logout_url' => $logoutUrl],
        ]);
    }

    public function user(): JsonResponse
    {
        $user = Auth::guard('web')->user();

        if (! $user || $user->status !== 'active' || $user->deactivated_at !== null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json(['data' => $this->present($user)]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $user = Auth::guard('web')->user();

        if (! $user || $user->status !== 'active' || $user->deactivated_at !== null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $preferences = $request->validate([
            'notifyDocuments' => ['required', 'boolean'],
            'notifyUncategorised' => ['required', 'boolean'],
            'defaultFinancialYear' => ['required', 'in:current,all'],
        ]);

        $user->forceFill(['preferences' => $preferences])->save();

        return response()->json(['data' => $preferences]);
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        $data = [
            'id' => $user->id,
            'email' => $user->email,
            'display_name' => $user->display_name,
            'identity_provider' => $user->identity_provider,
            'role' => $user->role,
            'preferences' => $user->preferences,
            'clients' => [],
        ];

        if ($user->isAccountant()) {
            $data['clients'] = $user->accountantGrants()
                ->with('owner')
                ->where('status', 'active')
                ->get()
                ->map(fn ($grant) => [
                    'owner_id' => $grant->owner_id,
                    'owner_name' => $grant->owner?->display_name ?? $grant->owner?->email,
                    'sections' => $grant->sections,
                    'financial_year_ids' => $grant->financial_year_ids,
                ])
                ->values()
                ->all();
        }

        return $data;
    }

    private function safeReturnUrl(mixed $value): string
    {
        if (
            ! is_string($value)
            || $value === ''
            || ! str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || str_contains($value, '\\')
            || preg_match('/[\r\n]/', $value) === 1
        ) {
            return '/dashboard';
        }

        return $value;
    }

    private function validateIdToken(string $idToken, string $nonce): object
    {
        $realm = (string) config('services.keycloak.realms');
        $baseUrl = rtrim((string) config('services.keycloak.base_url'), '/');
        $publicUrl = rtrim((string) config('services.keycloak.public_url'), '/');
        $clientId = (string) config('services.keycloak.client_id');
        $jwksUrl = $baseUrl.'/realms/'.rawurlencode($realm)
            .'/protocol/openid-connect/certs';
        $jwks = Http::acceptJson()->timeout(5)->get($jwksUrl)->throw()->json();

        if (! is_array($jwks)) {
            throw new DomainException('Keycloak returned an invalid signing key set.');
        }

        $headers = new \stdClass();
        $claims = JWT::decode(
            $idToken,
            JWK::parseKeySet($jwks, 'RS256'),
            $headers,
        );
        $expectedIssuer = $publicUrl.'/realms/'.$realm;
        $audiences = is_array($claims->aud ?? null)
            ? $claims->aud
            : [$claims->aud ?? null];

        if (
            ($headers->alg ?? null) !== 'RS256'
            || ($claims->iss ?? null) !== $expectedIssuer
            || ! in_array($clientId, $audiences, true)
            || (isset($claims->azp) && $claims->azp !== $clientId)
            || ! is_string($claims->nonce ?? null)
            || ! hash_equals($nonce, $claims->nonce)
            || ! is_string($claims->sub ?? null)
            || $claims->sub === ''
            || ! is_int($claims->iat ?? null)
            || $claims->iat > time() + 60
        ) {
            throw new DomainException('Keycloak ID token validation failed.');
        }

        return $claims;
    }

    private function keycloakLogoutUrl(string $idToken): string
    {
        $baseUrl = rtrim((string) config('services.keycloak.public_url'), '/');
        $realm = rawurlencode((string) config('services.keycloak.realms'));
        $query = http_build_query([
            'client_id' => config('services.keycloak.client_id'),
            'post_logout_redirect_uri' => rtrim((string) config('app.url'), '/').'/login',
            'id_token_hint' => $idToken,
        ], '', '&', PHP_QUERY_RFC3986);

        if ($baseUrl === '' || $realm === '') {
            throw new \RuntimeException('Keycloak logout URL is not configured.');
        }

        return $baseUrl.'/realms/'.$realm.'/protocol/openid-connect/logout?'.$query;
    }

    private function clearOidcSession(Request $request): void
    {
        $request->session()->forget([
            'keycloak.oidc.nonce',
            'keycloak.oidc.return_url',
            self::PENDING_ID_TOKEN_SESSION_KEY,
        ]);
    }
}