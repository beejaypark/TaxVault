<?php

namespace Tests\Unit\Infrastructure\OCR;

use App\Application\OCR\Data\OcrRequest;
use App\Infrastructure\OCR\Providers\HttpOcrProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HttpOcrProviderTest extends TestCase
{
    public function test_http_provider_uploads_document_and_maps_response(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault HTTP OCR test document';
        $sha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/invoice.pdf',
            $content
        );

        config([
            'ocr.http.endpoint' => 'https://ocr.example.test/v1/extract',
            'ocr.http.token' => 'test-token',
            'ocr.http.timeout' => 30,
            'ocr.http.connect_timeout' => 5,
        ]);

        Http::fake([
            'https://ocr.example.test/v1/extract' => Http::response([
                'provider' => 'generic-http',
                'model' => 'provider-neutral-model',
                'model_version' => '1',
                'extraction_version' => 'ocr-v1',
                'status' => 'completed',
                'output' => [
                    'text' => 'Invoice total $110.00',
                ],
                'quality_metadata' => [
                    'confidence' => 98.5,
                ],
                'error_metadata' => [],
            ], 200),
        ]);

        $request = new OcrRequest(
            documentId: '00000000-0000-0000-0000-000000000001',
            storageDisk: 'documents',
            objectKey: '2026-27/invoice.pdf',
            mimeType: 'application/pdf',
            originalFilename: 'invoice.pdf',
            contentSha256: $sha256,
        );

        $result = app(HttpOcrProvider::class)->process($request);

        $this->assertSame('generic-http', $result->provider);
        $this->assertSame(
            'provider-neutral-model',
            $result->model
        );
        $this->assertSame('1', $result->modelVersion);
        $this->assertSame('ocr-v1', $result->extractionVersion);
        $this->assertSame('completed', $result->status);

        $this->assertSame(
            'Invoice total $110.00',
            $result->output['text']
        );

        $this->assertSame(
            98.5,
            $result->qualityMetadata['confidence']
        );

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return $request->url() ===
                    'https://ocr.example.test/v1/extract'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer test-token'
                )
                && str_contains($body, 'name="document"')
                && str_contains($body, 'invoice.pdf');
        });
    }

    public function test_http_provider_rejects_missing_document(): void
    {
        Storage::fake('documents');

        config([
            'ocr.http.endpoint' => 'https://ocr.example.test/v1/extract',
        ]);

        $request = new OcrRequest(
            documentId: '00000000-0000-0000-0000-000000000001',
            storageDisk: 'documents',
            objectKey: '2026-27/missing.pdf',
            mimeType: 'application/pdf',
            originalFilename: 'missing.pdf',
            contentSha256: str_repeat('a', 64),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'OCR document object does not exist'
        );

        app(HttpOcrProvider::class)->process($request);
    }

    public function test_http_provider_rejects_content_hash_mismatch(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault OCR test document';

        Storage::disk('documents')->put(
            '2026-27/invoice.pdf',
            $content
        );

        config([
            'ocr.http.endpoint' => 'https://ocr.example.test/v1/extract',
        ]);

        $request = new OcrRequest(
            documentId: '00000000-0000-0000-0000-000000000001',
            storageDisk: 'documents',
            objectKey: '2026-27/invoice.pdf',
            mimeType: 'application/pdf',
            originalFilename: 'invoice.pdf',
            contentSha256: str_repeat('a', 64),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'content SHA-256 mismatch'
        );

        app(HttpOcrProvider::class)->process($request);
    }

    public function test_http_provider_rejects_unsupported_mime_type(): void
    {
        config([
            'ocr.http.endpoint' => 'https://ocr.example.test/v1/extract',
        ]);

        $request = new OcrRequest(
            documentId: '00000000-0000-0000-0000-000000000001',
            storageDisk: 'documents',
            objectKey: '2026-27/invoice.docx',
            mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            originalFilename: 'invoice.docx',
            contentSha256: str_repeat('a', 64),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Unsupported OCR document MIME type'
        );

        app(HttpOcrProvider::class)->process($request);
    }

    public function test_http_provider_rejects_failed_http_response(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault HTTP OCR test document';
        $sha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/invoice.pdf',
            $content
        );

        config([
            'ocr.http.endpoint' => 'https://ocr.example.test/v1/extract',
        ]);

        Http::fake([
            'https://ocr.example.test/v1/extract' =>
                Http::response([], 500),
        ]);

        $request = new OcrRequest(
            documentId: '00000000-0000-0000-0000-000000000001',
            storageDisk: 'documents',
            objectKey: '2026-27/invoice.pdf',
            mimeType: 'application/pdf',
            originalFilename: 'invoice.pdf',
            contentSha256: $sha256,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'OCR provider request failed with HTTP status 500'
        );

        app(HttpOcrProvider::class)->process($request);
    }

    public function test_http_provider_rejects_invalid_response(): void
    {
        Storage::fake('documents');

        $content = '%PDF-1.4 TaxVault HTTP OCR test document';
        $sha256 = hash('sha256', $content);

        Storage::disk('documents')->put(
            '2026-27/invoice.pdf',
            $content
        );

        config([
            'ocr.http.endpoint' => 'https://ocr.example.test/v1/extract',
        ]);

        Http::fake([
            'https://ocr.example.test/v1/extract' =>
                Http::response([
                    'provider' => 'generic-http',
                ], 200),
        ]);

        $request = new OcrRequest(
            documentId: '00000000-0000-0000-0000-000000000001',
            storageDisk: 'documents',
            objectKey: '2026-27/invoice.pdf',
            mimeType: 'application/pdf',
            originalFilename: 'invoice.pdf',
            contentSha256: $sha256,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'OCR provider response is missing status'
        );

        app(HttpOcrProvider::class)->process($request);
    }

    public function test_http_provider_requires_endpoint_configuration(): void
    {
        Storage::fake('documents');

        config([
            'ocr.http.endpoint' => null,
        ]);

        $request = new OcrRequest(
            documentId: '00000000-0000-0000-0000-000000000001',
            storageDisk: 'documents',
            objectKey: '2026-27/invoice.pdf',
            mimeType: 'application/pdf',
            originalFilename: 'invoice.pdf',
            contentSha256: str_repeat('a', 64),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'OCR HTTP endpoint is not configured'
        );

        app(HttpOcrProvider::class)->process($request);
    }
}
