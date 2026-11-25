<?php

namespace App\Http\Controllers\Api;

use App\Domain\Documents\Models\StorageProviderSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StorageProviderSettingController extends Controller
{
    public function show(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['onedrive', 'google_drive'], true), 404);

        $setting = StorageProviderSetting::query()
            ->where('user_id', $request->user()->getKey())
            ->where('provider', $provider)
            ->first();

        return response()->json(['data' => $this->payload($request, $setting)]);
    }

    public function update(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['onedrive', 'google_drive'], true), 404);

        $existing = StorageProviderSetting::query()
            ->where('user_id', $request->user()->getKey())
            ->where('provider', $provider)
            ->first();

        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => [$existing ? 'nullable' : 'required', 'string', 'max:2048'],
            'tenant' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = $existing ?? new StorageProviderSetting([
            'user_id' => $request->user()->getKey(),
            'provider' => $provider,
        ]);
        $setting->client_id = $data['client_id'];
        if (isset($data['client_secret']) && $data['client_secret'] !== '') {
            $setting->client_secret = $data['client_secret'];
        }
        $setting->tenant = $data['tenant'] ?? ($provider === 'google_drive' ? 'google' : 'common');
        $setting->save();

        return response()->json(['data' => $this->payload($request, $setting)]);
    }

    private function payload(Request $request, ?StorageProviderSetting $setting): array
    {
        return [
            'provider' => $setting?->provider ?? $request->route('provider'),
            'configured' => $setting !== null && $setting->client_id !== '' && $setting->client_secret !== '',
            'client_id' => $setting?->client_id ?? '',
            'tenant' => $setting?->tenant ?? 'common',
            'redirect_uri' => rtrim((string) config('app.url'), '/').'/api/document-storage/'.($setting?->provider ?? $request->route('provider')).'/callback',
        ];
    }
}
