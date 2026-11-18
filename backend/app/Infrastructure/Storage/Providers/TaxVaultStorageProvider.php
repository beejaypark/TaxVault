<?php

namespace App\Infrastructure\Storage\Providers;

use App\Application\Documents\Contracts\DocumentStorageProvider;
use App\Domain\Documents\Models\Document;

final class TaxVaultStorageProvider implements DocumentStorageProvider
{
    public function key(): string
    {
        return Document::PROVIDER_TAXVAULT;
    }

    public function label(): string
    {
        return 'TaxVault secure storage';
    }

    public function descriptor(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'mode' => 'managed',
            'supports_import' => false,
            'supports_sync' => false,
            'configured' => true,
        ];
    }
}
