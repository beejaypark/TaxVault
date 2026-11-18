<?php

namespace App\Application\Documents;

use App\Application\Documents\Contracts\DocumentStorageProvider;
use InvalidArgumentException;

final class StorageProviderRegistry
{
    /** @var list<DocumentStorageProvider> */
    private readonly array $providers;

    /** @param iterable<DocumentStorageProvider> $providers */
    public function __construct(iterable $providers)
    {
        $this->providers = is_array($providers)
            ? array_values($providers)
            : array_values(iterator_to_array($providers));
    }

    /** @return list<array{key: string, label: string, mode: string, supports_import: bool, supports_sync: bool}> */
    public function descriptors(): array
    {
        $descriptors = array_values(array_map(
            static fn (DocumentStorageProvider $provider): array => $provider->descriptor(),
            $this->providers,
        ));

        $registeredKeys = array_column($descriptors, 'key');
        $catalog = [
            ['key' => 'google_drive', 'label' => 'Google Drive'],
            ['key' => 'dropbox', 'label' => 'Dropbox'],
            ['key' => 'box', 'label' => 'Box'],
            ['key' => 'sharepoint', 'label' => 'SharePoint'],
        ];

        foreach ($catalog as $provider) {
            if (in_array($provider['key'], $registeredKeys, true)) {
                continue;
            }

            $descriptors[] = [
                ...$provider,
                'mode' => 'external',
                'supports_import' => false,
                'supports_sync' => false,
                'configured' => false,
            ];
        }

        return $descriptors;
    }

    public function get(string $key): DocumentStorageProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->key() === $key) {
                return $provider;
            }
        }

        throw new InvalidArgumentException("Unsupported document storage provider: {$key}");
    }
}
