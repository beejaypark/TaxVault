<?php

namespace App\Infrastructure\Storage\Providers;

use App\Application\Documents\Contracts\DocumentStorageProvider;

final class DropboxStorageProvider implements DocumentStorageProvider
{
    public function key(): string { return 'dropbox'; }
    public function label(): string { return 'Dropbox'; }
    public function descriptor(): array { return ['key' => $this->key(), 'label' => $this->label(), 'mode' => 'external', 'supports_import' => false, 'supports_sync' => false, 'configured' => false]; }
}
