<?php

namespace App\Application\Documents\Contracts;

interface DocumentStorageProvider
{
    public function key(): string;

    public function label(): string;

    /** @return array{key: string, label: string, mode: string, supports_import: bool, supports_sync: bool} */
    public function descriptor(): array;
}
