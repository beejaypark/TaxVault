<?php

namespace App\Http\Controllers\Api;

use App\Application\Documents\StorageProviderRegistry;
use App\Application\Documents\OneDriveClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class DocumentStorageProviderController extends Controller
{
    public function index(\Illuminate\Http\Request $request, StorageProviderRegistry $registry, OneDriveClient $client): JsonResponse
    {
        $providers = $registry->descriptors();
        $setting = $client->settingForUser($request->user());
        $configured = $client->isConfigured($setting);
        $providers = array_map(static function (array $provider) use ($configured): array {
            if ($provider['key'] !== 'onedrive') {
                return $provider;
            }
            $provider['configured'] = $configured;
            $provider['supports_import'] = $configured;
            $provider['supports_sync'] = $configured;
            return $provider;
        }, $providers);

        return response()->json([
            'data' => $providers,
            'default' => (string) config('documents.default_provider', 'taxvault'),
        ]);
    }
}
