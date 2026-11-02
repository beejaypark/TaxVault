<?php

namespace App\Infrastructure\OCR\Providers;

use App\Application\OCR\Contracts\OcrProvider;
use App\Application\OCR\Data\OcrRequest;
use App\Application\OCR\Data\OcrResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

final class LocalOcrProvider implements OcrProvider
{
    private const MAX_PDF_PAGES = 50;

    private const MAX_TEXT_LENGTH = 30000;

    public function process(OcrRequest $request): OcrResult
    {
        $this->assertSupportedMimeType($request->mimeType);

        $content = Storage::disk($request->storageDisk)->get($request->objectKey);

        if (! hash_equals($request->contentSha256, hash('sha256', $content))) {
            throw new RuntimeException(
                sprintf('OCR document content SHA-256 mismatch for document %s.', $request->documentId),
            );
        }

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'taxvault-ocr-'.Str::uuid();

        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Unable to prepare local OCR workspace.');
        }

        try {
            $extension = $request->mimeType === 'application/pdf'
                ? '.pdf'
                : $this->imageExtension($request->mimeType);
            $inputPath = $directory.DIRECTORY_SEPARATOR.'source'.$extension;

            if (file_put_contents($inputPath, $content) === false) {
                throw new RuntimeException('Unable to prepare the document for OCR.');
            }

            $text = $request->mimeType === 'application/pdf'
                ? $this->extractPdfText($inputPath, $directory)
                : $this->runTesseract($inputPath);

            $text = trim(preg_replace('/\R/u', "\n", $text) ?? '');

            if ($text === '') {
                throw new RuntimeException('No readable text was found in the document.');
            }

            return new OcrResult(
                provider: 'local',
                model: $request->mimeType === 'application/pdf' ? 'Poppler + Tesseract' : 'Tesseract',
                modelVersion: null,
                extractionVersion: 'local-ocr-v1',
                status: 'completed',
                output: [
                    'text' => mb_substr($text, 0, self::MAX_TEXT_LENGTH),
                    'normalized' => [
                        'schema_version' => 'ocr-extraction-v1',
                        'fields' => $this->normalizedFields($text),
                    ],
                ],
                qualityMetadata: [
                    'adapter' => self::class,
                    'document_id' => $request->documentId,
                    'mime_type' => $request->mimeType,
                    'content_sha256' => $request->contentSha256,
                    'text_truncated' => mb_strlen($text) > self::MAX_TEXT_LENGTH,
                ],
            );
        } finally {
            $this->removeWorkspace($directory);
        }
    }

    private function extractPdfText(string $path, string $directory): string
    {
        $info = new Process(['pdfinfo', $path]);
        $info->setTimeout(30);
        $info->mustRun();

        if (preg_match('/^Pages:\s+(\d+)/mi', $info->getOutput(), $matches) !== 1) {
            throw new RuntimeException('Unable to determine the PDF page count.');
        }

        $pageCount = (int) $matches[1];

        if ($pageCount < 1 || $pageCount > self::MAX_PDF_PAGES) {
            throw new RuntimeException(
                sprintf('PDF must contain between 1 and %d pages.', self::MAX_PDF_PAGES),
            );
        }

        $textProcess = new Process(['pdftotext', '-layout', '-nopgbrk', '-enc', 'UTF-8', $path, '-']);
        $textProcess->setTimeout(60);
        $textProcess->mustRun();
        $text = trim($textProcess->getOutput());

        if ($text !== '') {
            return $text;
        }

        $prefix = $directory.DIRECTORY_SEPARATOR.'page';
        $render = new Process([
            'pdftoppm',
            '-png',
            '-r',
            '200',
            '-f',
            '1',
            '-l',
            (string) $pageCount,
            $path,
            $prefix,
        ]);
        $render->setTimeout(120);
        $render->mustRun();

        $pages = glob($prefix.'-*.png');

        if ($pages === false || $pages === []) {
            throw new RuntimeException('Unable to render scanned PDF pages for OCR.');
        }

        sort($pages, SORT_NATURAL);

        $texts = [];
        foreach ($pages as $page) {
            $texts[] = $this->runTesseract($page);
        }

        return implode("\n", $texts);
    }

    private function runTesseract(string $path): string
    {
        $process = new Process(['tesseract', $path, 'stdout', '-l', 'eng']);
        $process->setTimeout(60);
        $process->mustRun();

        return $process->getOutput();
    }

    /**
     * @return array<string, array{value: mixed, confidence: float, source: string}>
     */
    private function normalizedFields(string $text): array
    {
        $fields = [
            'document_text' => [
                'value' => mb_substr($text, 0, self::MAX_TEXT_LENGTH),
                'confidence' => 80.0,
                'source' => 'local_ocr',
            ],
        ];

        if (preg_match('/Brisbane City Council/i', $text) === 1) {
            $fields['supplier'] = [
                'value' => 'Brisbane City Council',
                'confidence' => 95.0,
                'source' => 'local_ocr',
            ];
        }

        $this->addLabeledDate(
            $fields,
            $text,
            'invoice_date',
            '(?:issue date|date issued|invoice date)\s*:?\s*',
        );
        $this->addLabeledDate(
            $fields,
            $text,
            'due_date',
            '(?:due date|payment due)\s*:?\s*',
        );
        $this->addLabeledAmount($fields, $text);

        return $fields;
    }

    private function addLabeledDate(array &$fields, string $text, string $field, string $labelPattern): void
    {
        $datePattern = '(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}|\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})';

        if (preg_match('/'.$labelPattern.$datePattern.'/i', $text, $matches) === 1) {
            $dateFormats = str_contains($matches[1], '/')
                ? ['!d/m/Y', '!d/m/y']
                : (str_contains($matches[1], '-')
                    ? ['!d-m-Y', '!d-m-y']
                    : ['!j F Y', '!j M Y']);

            foreach ($dateFormats as $format) {
                $date = DateTimeImmutable::createFromFormat($format, $matches[1]);
                $errors = DateTimeImmutable::getLastErrors();

                if (
                    $date !== false
                    && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                ) {
                    $fields[$field] = [
                        'value' => $date->format('Y-m-d'),
                        'confidence' => 65.0,
                        'source' => 'local_ocr_candidate',
                    ];

                    return;
                }
            }
        }
    }

    private function addLabeledAmount(array &$fields, string $text): void
    {
        $amountPattern = '\$?\s*(\d{1,3}(?:,\d{3})*\.\d{2}|\d+\.\d{2})';
        $labelPattern = '/(?:net(?:t)?\s+amount\s+payable|total\s+(?:amount\s+)?due|amount\s+due|pay\s+this\s+amount|total\s+payable|balance\s+due)\s*:?\s*'.$amountPattern.'/i';

        if (preg_match($labelPattern, $text, $matches) === 1) {
            $fields['total_amount'] = [
                'value' => str_replace(',', '', $matches[1]),
                'confidence' => 65.0,
                'source' => 'local_ocr_candidate',
            ];
        }
    }

    private function imageExtension(?string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            'image/tiff' => '.tiff',
            default => throw new RuntimeException('Unsupported image type for local OCR.'),
        };
    }

    private function assertSupportedMimeType(?string $mimeType): void
    {
        if (! in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/tiff'], true)) {
            throw new RuntimeException(
                sprintf('Unsupported OCR document MIME type: %s', $mimeType ?? 'null'),
            );
        }
    }

    private function removeWorkspace(string $directory): void
    {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
