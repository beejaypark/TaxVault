<?php

namespace Tests\Unit\Application\OCR;

use App\Application\OCR\Data\NormalizedExtraction;
use PHPUnit\Framework\TestCase;

class NormalizedExtractionTest extends TestCase
{
    public function test_normalized_extraction_contains_schema_version_and_fields(): void
    {
        $extraction = new NormalizedExtraction(
            schemaVersion: 'ocr-extraction-v1',
            fields: [
                'supplier' => [
                    'value' => 'Example Pty Ltd',
                    'confidence' => 98.5,
                    'source' => 'ocr',
                ],
            ],
        );

        $this->assertEquals(
            'ocr-extraction-v1',
            $extraction->schemaVersion
        );

        $this->assertEquals(
            'Example Pty Ltd',
            $extraction->fields['supplier']['value']
        );

        $this->assertEquals(
            98.5,
            $extraction->fields['supplier']['confidence']
        );

        $this->assertEquals(
            'ocr',
            $extraction->fields['supplier']['source']
        );
    }

    public function test_normalized_extraction_can_be_serialized(): void
    {
        $extraction = new NormalizedExtraction(
            schemaVersion: 'ocr-extraction-v1',
            fields: [
                'amount' => [
                    'value' => 110.00,
                    'confidence' => 99.0,
                    'source' => 'ocr',
                ],
            ],
        );

        $payload = $extraction->toArray();

        $this->assertEquals(
            'ocr-extraction-v1',
            $payload['schema_version']
        );

        $this->assertEquals(
            110.00,
            $payload['fields']['amount']['value']
        );
    }
}
