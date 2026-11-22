<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NewUserRegisteredMail;
use App\Models\DevelopmentAdminCredential;
use App\Models\User;
use App\Support\SessionActivity;
use DomainException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Socialite\Facades\Socialite;
use UnexpectedValueException;

class AuthController extends Controller
{
    private const PENDING_ID_TOKEN_SESSION_KEY = 'keycloak.oidc.pending_id_token';
    private const ID_TOKEN_SESSION_KEY = 'keycloak.oidc.id_token';

    public function __construct(private readonly SessionActivity $activity) {}

    private function developmentLoginEnabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('dev_admin.enabled', false);
    }

    public function configuration(): JsonResponse
    {
        return response()->json([
            'data' => [
                'development_login_enabled' => $this->developmentLoginEnabled(),
            ],
        ]);
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
        $this->activity->touch($request->session());

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
        $providerParameters = ['nonce' => $nonce];
        $providerParameters['prompt'] = $request->boolean('register')
            ? 'create'
            : 'login';
        if ($request->query('provider') === 'google') {
            $providerParameters['kc_idp_hint'] = 'google';
        }
        $provider->with($providerParameters);
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

        $isNewUser = ! $user->exists;
        $user->save();

        if ($isNewUser && is_string(config('admin.notification_email'))
            && filter_var(config('admin.notification_email'), FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to(config('admin.notification_email'))->send(new NewUserRegisteredMail(
                    displayName: (string) ($user->display_name ?: $user->email ?: 'New customer'),
                    email: (string) ($user->email ?: 'Not provided'),
                    provider: (string) $user->identity_provider,
                    registeredAt: (string) $user->created_at,
                ));
            } catch (\Throwable $exception) {
                Log::warning('New customer notification email failed.', [
                    'user_id' => $user->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->activity->touch($request->session());
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
            ? $this->keycloakLogoutUrl(Crypt::decryptString($idToken), $request)
            : rtrim($request->getSchemeAndHttpHost(), '/').'/';

        $this->activity->forget($request->session());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out.',
            'data' => ['logout_url' => $logoutUrl],
        ]);
    }

    public function activity(Request $request): Response|JsonResponse
    {
        if (! $this->activity->renew($request->session())) {
            return response()->json(['message' => 'Your session has expired.', 'code' => 'session_expired'], 401);
        }

        return response()->noContent();
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
            'language' => ['sometimes', 'in:en,kr,cn,es'],
            'notifyDocuments' => ['required', 'boolean'],
            'notifyUncategorised' => ['required', 'boolean'],
            'notifyTaxNews' => ['sometimes', 'boolean'],
            'defaultFinancialYear' => ['required', 'in:current,all'],
            'emailReviewDigest' => ['sometimes', 'boolean'],
            'emailDeadlineReminders' => ['sometimes', 'boolean'],
            'lodgementDeadline' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'dashboardLayout' => ['sometimes', 'array', 'max:20'],
            'dashboardLayout.*.id' => ['required', 'string', 'max:80'],
            'dashboardLayout.*.visible' => ['required', 'boolean'],
            'dashboardLayout.*.order' => ['required', 'integer', 'min:0', 'max:100'],
            'ai' => ['sometimes', 'array'],
            'ai.enabled' => ['sometimes', 'boolean'],
            'ai.provider' => ['sometimes', 'in:openai,anthropic,gemini'],
            'ai.model' => ['sometimes', 'string', 'max:100'],
            'ai.api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'ai.api_key_configured' => ['sometimes', 'boolean'],
            'ai.clear_api_key' => ['sometimes', 'boolean'],
        ]);

        $aiInput = $preferences['ai'] ?? [];
        $storedAi = $user->ai_settings ?? [];
        $previousProvider = $storedAi['provider'] ?? null;
        if (array_key_exists('provider', $aiInput) && $previousProvider !== null && $previousProvider !== $aiInput['provider'] && !array_key_exists('api_key', $aiInput)) {
            unset($storedAi['api_key']);
        }
        if (($aiInput['clear_api_key'] ?? false) === true) {
            unset($storedAi['api_key']);
        } elseif (array_key_exists('api_key', $aiInput) && trim((string) $aiInput['api_key']) !== '') {
            $storedAi['api_key'] = trim((string) $aiInput['api_key']);
        }
        if (array_key_exists('enabled', $aiInput)) $storedAi['enabled'] = (bool) $aiInput['enabled'];
        if (array_key_exists('provider', $aiInput)) $storedAi['provider'] = (string) $aiInput['provider'];
        if (array_key_exists('model', $aiInput)) $storedAi['model'] = (string) $aiInput['model'];
        $user->forceFill(['ai_settings' => $storedAi])->save();

        $preferences = array_merge($user->preferences ?? [], $preferences);
        $preferences['ai'] = [
            'enabled' => (bool) ($storedAi['enabled'] ?? true),
            'provider' => (string) ($storedAi['provider'] ?? 'openai'),
            'model' => (string) ($storedAi['model'] ?? config('services.openai.tax_news_model', 'gpt-4o-mini')),
            'api_key_configured' => isset($storedAi['api_key']) && $storedAi['api_key'] !== '',
        ];

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
            'preferences' => array_merge($user->preferences ?? [], [
                'ai' => [
                    'enabled' => (bool) (($user->ai_settings ?? [])['enabled'] ?? true),
                    'provider' => (string) (($user->ai_settings ?? [])['provider'] ?? 'openai'),
                    'model' => (string) (($user->ai_settings ?? [])['model'] ?? config('services.openai.tax_news_model', 'gpt-4o-mini')),
                    'api_key_configured' => isset(($user->ai_settings ?? [])['api_key']),
                ],
            ]),
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

    private function keycloakLogoutUrl(string $idToken, Request $request): string
    {
        $baseUrl = rtrim((string) config('services.keycloak.public_url'), '/');
        $realm = rawurlencode((string) config('services.keycloak.realms'));
        $query = http_build_query([
            'client_id' => config('services.keycloak.client_id'),
            'post_logout_redirect_uri' => rtrim($request->getSchemeAndHttpHost(), '/').'/',
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
