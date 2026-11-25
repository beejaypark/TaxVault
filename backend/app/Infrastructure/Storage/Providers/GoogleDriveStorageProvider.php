<?php

namespace App\Infrastructure\Storage\Providers;

use App\Application\Documents\Contracts\DocumentStorageProvider;

final class GoogleDriveStorageProvider implements DocumentStorageProvider
{
    public function key(): string { return 'google_drive'; }
    public function label(): string { return 'Google Drive'; }
    public function descriptor(): array
    {
        return ['key' => $this->key(), 'label' => $this->label(), 'mode' => 'external', 'supports_import' => false, 'supports_sync' => false, 'configured' => false];
    }
}
