<?php

namespace App\Application\OCR\Data;

final readonly class OcrRequest
{
    public function __construct(
        public string $documentId,
        public string $storageDisk,
        public string $objectKey,
        public ?string $mimeType,
        public ?string $originalFilename,
        public string $contentSha256,
        public array $metadata = [],
    ) {
    }
}
