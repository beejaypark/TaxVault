<?php

namespace App\Infrastructure\OCR\Providers;

use App\Application\OCR\Contracts\OcrProvider;
use App\Application\OCR\Data\OcrRequest;
use App\Application\OCR\Data\OcrResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

final class HttpOcrProvider implements OcrProvider
{
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

        $endpoint = config('ocr.http.endpoint');

        if (! is_string($endpoint) || trim($endpoint) === '') {
            throw new RuntimeException(
                'OCR HTTP endpoint is not configured.'
            );
        }

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

        $response = $this->client()
            ->attach(
                'document',
                $content,
                $request->originalFilename ?? basename($request->objectKey),
            )
            ->post($endpoint, [
                'document_id' => $request->documentId,
                'mime_type' => $request->mimeType,
                'original_filename' => $request->originalFilename,
                'content_sha256' => $actualSha256,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf(
                    'OCR provider request failed with HTTP status %d.',
                    $response->status(),
                ),
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException(
                'OCR provider returned an invalid JSON response.'
            );
        }

        $status = $payload['status'] ?? null;

        if (! is_string($status) || $status === '') {
            throw new RuntimeException(
                'OCR provider response is missing status.'
            );
        }

        return new OcrResult(
            provider: is_string($payload['provider'] ?? null)
                ? $payload['provider']
                : 'http',
            model: is_string($payload['model'] ?? null)
                ? $payload['model']
                : null,
            modelVersion: is_string($payload['model_version'] ?? null)
                ? $payload['model_version']
                : null,
            extractionVersion: is_string($payload['extraction_version'] ?? null)
                ? $payload['extraction_version']
                : 'ocr-v1',
            status: $status,
            output: is_array($payload['output'] ?? null)
                ? $payload['output']
                : [],
            qualityMetadata: is_array($payload['quality_metadata'] ?? null)
                ? $payload['quality_metadata']
                : [],
            errorMetadata: is_array($payload['error_metadata'] ?? null)
                ? $payload['error_metadata']
                : [],
        );
    }

    /**
     * @return list<string>
     */
    public static function supportedMimeTypes(): array
    {
        return self::SUPPORTED_MIME_TYPES;
    }

    private function client(): PendingRequest
    {
        $client = Http::timeout(
            (int) config('ocr.http.timeout', 60)
        )->connectTimeout(
            (int) config('ocr.http.connect_timeout', 10)
        )->acceptJson();

        $token = config('ocr.http.token');

        if (is_string($token) && trim($token) !== '') {
            $client = $client->withToken($token);
        }

        return $client;
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