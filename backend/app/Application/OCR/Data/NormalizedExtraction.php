<?php

namespace App\Application\OCR\Data;

final readonly class NormalizedExtraction
{
    public function __construct(
        public string $schemaVersion,
        public array $fields = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'fields' => $this->fields,
        ];
    }
}
