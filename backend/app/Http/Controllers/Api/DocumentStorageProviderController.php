<?php

namespace App\Http\Controllers\Api;

use App\Application\Documents\StorageProviderRegistry;
use App\Application\Documents\OneDriveClient;
use App\Application\Documents\GoogleDriveClient;
use App\Application\Documents\DropboxClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class DocumentStorageProviderController extends Controller
{
    public function index(\Illuminate\Http\Request $request, StorageProviderRegistry $registry, OneDriveClient $client, GoogleDriveClient $googleDrive, DropboxClient $dropbox): JsonResponse
    {
        $providers = $registry->descriptors();
        $setting = $client->settingForUser($request->user());
        $configured = $client->isConfigured($setting);
        $googleSetting = $googleDrive->settingForUser($request->user());
        $googleConfigured = $googleDrive->isConfigured($googleSetting);
        $dropboxSetting = $dropbox->settingForUser($request->user());
        $dropboxConfigured = $dropbox->isConfigured($dropboxSetting);
        $providers = array_map(static function (array $provider) use ($configured): array {
            if ($provider['key'] !== 'onedrive') {
                return $provider;
            }
            $provider['configured'] = $configured;
            $provider['supports_import'] = $configured;
            $provider['supports_sync'] = $configured;
            return $provider;
        }, $providers);
        $providers = array_map(static function (array $provider) use ($dropboxConfigured): array {
            if ($provider['key'] === 'dropbox') { $provider['configured'] = $dropboxConfigured; $provider['supports_import'] = $dropboxConfigured; $provider['supports_sync'] = $dropboxConfigured; }
            return $provider;
        }, $providers);
        $providers = array_map(static function (array $provider) use ($googleConfigured): array {
            if ($provider['key'] === 'google_drive') {
                $provider['configured'] = $googleConfigured;
                $provider['supports_import'] = $googleConfigured;
                $provider['supports_sync'] = $googleConfigured;
            }
            return $provider;
        }, $providers);

        return response()->json([
            'data' => $providers,
            'default' => (string) config('documents.default_provider', 'taxvault'),
        ]);
    }
}
