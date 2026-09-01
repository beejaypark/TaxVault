<?php

namespace App\Infrastructure\OCR\Providers;

use App\Application\OCR\Contracts\OcrProvider;
use App\Application\OCR\Data\OcrRequest;
use App\Application\OCR\Data\OcrResult;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

final class TestOcrProvider implements OcrProvider
{
    /**
     * OCR-003 approved document MIME types.
     *
     * These are the document formats supported by the adapter boundary.
     * Actual external OCR execution remains behind this provider contract.
     */
    private const SUPPORTED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/tiff',
    ];

    public function process(OcrRequest $request): OcrResult
    {
        $this->assertSupportedMimeType($request->mimeType);

        $disk = Storage::disk($request->storageDisk);

        if (! $disk->exists($request->objectKey)) {
            throw new RuntimeException(
                sprintf(
                    'OCR document object does not exist: %s:%s',
                    $request->storageDisk,
                    $request->objectKey,
                ),
            );
        }

        $content = $disk->get($request->objectKey);

        $actualSha256 = hash('sha256', $content);

        if (! hash_equals($request->contentSha256, $actualSha256)) {
            throw new RuntimeException(
                sprintf(
                    'OCR document content SHA-256 mismatch for document %s.',
                    $request->documentId,
                ),
            );
        }

        $text = sprintf(
            'OCR test extraction for document %s',
            $request->documentId,
        );

        return new OcrResult(
            provider: 'test',
            model: 'test-ocr',
            modelVersion: '1',
            extractionVersion: 'ocr-v1',
            status: 'completed',
            output: [
                /*
                 * OCR-003 backward-compatible output.
                 *
                 * Keep this top-level text field so existing consumers
                 * and tests remain compatible.
                 */
                'text' => $text,

                /*
                 * OCR-004 normalized extraction.
                 */
                'normalized' => [
                    'schema_version' => 'ocr-extraction-v1',
                    'fields' => [
                        'text' => [
                            'value' => $text,
                            'confidence' => 100.0,
                            'source' => 'ocr',
                        ],
                    ],
                ],
            ],
            qualityMetadata: [
                'confidence' => 100.0,
                'adapter' => self::class,
                'document_id' => $request->documentId,
                'mime_type' => $request->mimeType,
                'storage_disk' => $request->storageDisk,
                'object_key' => $request->objectKey,
                'content_sha256' => $actualSha256,
                'content_size_bytes' => strlen($content),
            ],
        );
    }

    /**
     * @return list<string>
     */
    public static function supportedMimeTypes(): array
    {
        return self::SUPPORTED_MIME_TYPES;
    }

    private function assertSupportedMimeType(?string $mimeType): void
    {
        if (
            $mimeType === null ||
            ! in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported OCR document MIME type: %s',
                    $mimeType ?? 'null',
                ),
            );
        }
    }
}