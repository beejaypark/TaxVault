<?php

namespace App\Application\Documents;

use App\Domain\Documents\Models\Document;
use RuntimeException;

final class DuplicateDocumentException extends RuntimeException
{
    public function __construct(
        public readonly Document $existingDocument,
    ) {
        parent::__construct('This file has already been uploaded.');
    }
}
