<?php

namespace App\Application\OCR\Contracts;

use App\Application\OCR\Data\OcrRequest;
use App\Application\OCR\Data\OcrResult;

interface OcrProvider
{
    public function process(OcrRequest $request): OcrResult;
}
