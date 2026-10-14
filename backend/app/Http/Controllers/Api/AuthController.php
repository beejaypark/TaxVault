<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevelopmentAdminCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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
            return response()->json(['message' => 'The supplied credentials are invalid.'], 422);
        }

        $user = $credential->user;

        if (! $user || $user->status !== 'active' || $user->deactivated_at !== null) {
            return response()->json(['message' => 'The supplied credentials are invalid.'], 422);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'identity_provider' => $user->identity_provider,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if (! $this->developmentLoginEnabled()) {
            return response()->json(['message' => 'Development login is disabled.'], 404);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function user(): JsonResponse
    {
        if (! $this->developmentLoginEnabled()) {
            return response()->json(['message' => 'Development login is disabled.'], 404);
        }

        $user = Auth::guard('web')->user();

        if (! $user || $user->status !== 'active' || $user->deactivated_at !== null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'identity_provider' => $user->identity_provider,
            ],
        ]);
    }
}