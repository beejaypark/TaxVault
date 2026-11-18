<?php

namespace App\Infrastructure\Storage\Providers;

use App\Application\Documents\Contracts\DocumentStorageProvider;

final class OneDriveStorageProvider implements DocumentStorageProvider
{
    public function key(): string
    {
        return 'onedrive';
    }

    public function label(): string
    {
        return 'Microsoft OneDrive';
    }

    public function descriptor(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'mode' => 'external',
            'supports_import' => false,
            'supports_sync' => false,
            'configured' => false,
        ];
    }
}
