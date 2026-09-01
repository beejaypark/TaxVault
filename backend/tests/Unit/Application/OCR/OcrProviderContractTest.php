<?php

namespace Tests\Unit\Application\OCR;

use App\Application\OCR\Contracts\OcrProvider;
use App\Application\OCR\Data\OcrRequest;
use App\Application\OCR\Data\OcrResult;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OcrProviderContractTest extends TestCase
{
    public function test_ocr_provider_is_an_interface(): void
    {
        $reflection = new ReflectionClass(OcrProvider::class);

        $this->assertTrue($reflection->isInterface());
    }

    public function test_ocr_provider_exposes_process_contract(): void
    {
        $reflection = new ReflectionClass(OcrProvider::class);
        $method = $reflection->getMethod('process');

        $this->assertSame(
            OcrResult::class,
            $method->getReturnType()?->getName()
        );

        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertSame(
            OcrRequest::class,
            $parameters[0]->getType()?->getName()
        );
    }

    public function test_ocr_request_contains_document_identity_and_storage_metadata(): void
    {
        $request = new OcrRequest(
            documentId: '00000000-0000-0000-0000-000000000001',
            storageDisk: 'documents',
            objectKey: '2026/08/invoice.pdf',
            mimeType: 'application/pdf',
            originalFilename: 'invoice.pdf',
            contentSha256: str_repeat('a', 64),
        );

        $this->assertSame(
            '00000000-0000-0000-0000-000000000001',
            $request->documentId
        );

        $this->assertSame('documents', $request->storageDisk);
        $this->assertSame('2026/08/invoice.pdf', $request->objectKey);
        $this->assertSame('application/pdf', $request->mimeType);
        $this->assertSame('invoice.pdf', $request->originalFilename);
        $this->assertSame(str_repeat('a', 64), $request->contentSha256);
    }

    public function test_ocr_result_contains_extraction_metadata_and_output(): void
    {
        $result = new OcrResult(
            provider: 'test',
            model: 'test-ocr',
            modelVersion: '1',
            extractionVersion: 'ocr-v1',
            status: 'completed',
            output: [
                'text' => 'Invoice total $110.00',
            ],
            qualityMetadata: [
                'confidence' => 98.5,
            ],
        );

        $this->assertSame('test', $result->provider);
        $this->assertSame('test-ocr', $result->model);
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
    }
}
