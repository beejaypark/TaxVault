<?php

namespace App\Application\OCR\Data;

final readonly class OcrResult
{
    public function __construct(
        public string $provider,
        public ?string $model,
        public ?string $modelVersion,
        public string $extractionVersion,
        public string $status,
        public array $output = [],
        public array $qualityMetadata = [],
        public array $errorMetadata = [],
    ) {
    }
}
