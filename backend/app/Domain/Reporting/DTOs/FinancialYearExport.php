<?php

namespace App\Domain\Reporting\DTOs;

final readonly class FinancialYearExport
{
    public function __construct(
        public array $payload,
        public string $json,
        public string $sha256,
    ) {}
}
