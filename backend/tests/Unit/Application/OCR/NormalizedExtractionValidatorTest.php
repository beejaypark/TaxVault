<?php

namespace Tests\Unit\Application\OCR;

use App\Application\OCR\Data\NormalizedExtraction;
use App\Application\OCR\Validation\NormalizedExtractionValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NormalizedExtractionValidatorTest extends TestCase
{
    public function test_valid_extraction_passes_validation(): void
    {
        $extraction = new NormalizedExtraction(
            schemaVersion: 'ocr-extraction-v1',
            fields: [
                'supplier' => [
                    'value' => 'Example Pty Ltd',
                    'confidence' => 98.5,
                    'source' => 'ocr',
                ],
                'amount' => [
                    'value' => 110.00,
                    'confidence' => 99.0,
                    'source' => 'ocr',
                ],
            ],
        );

        $validated = (new NormalizedExtractionValidator())
            ->validate($extraction);

        $this->assertSame($extraction, $validated);
    }

    public function test_missing_schema_version_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NormalizedExtractionValidator())->validate(
            new NormalizedExtraction(
                schemaVersion: '',
                fields: [
                    'supplier' => [
                        'value' => 'Example Pty Ltd',
                        'confidence' => 98.5,
                        'source' => 'ocr',
                    ],
                ],
            )
        );
    }

    public function test_missing_required_field_metadata_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NormalizedExtractionValidator())->validate(
            new NormalizedExtraction(
                schemaVersion: 'ocr-extraction-v1',
                fields: [
                    'supplier' => [
                        'value' => 'Example Pty Ltd',
                        'confidence' => 98.5,
                    ],
                ],
            )
        );
    }

    public function test_invalid_confidence_below_zero_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NormalizedExtractionValidator())->validate(
            new NormalizedExtraction(
                schemaVersion: 'ocr-extraction-v1',
                fields: [
                    'supplier' => [
                        'value' => 'Example Pty Ltd',
                        'confidence' => -1,
                        'source' => 'ocr',
                    ],
                ],
            )
        );
    }

    public function test_invalid_confidence_above_one_hundred_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NormalizedExtractionValidator())->validate(
            new NormalizedExtraction(
                schemaVersion: 'ocr-extraction-v1',
                fields: [
                    'supplier' => [
                        'value' => 'Example Pty Ltd',
                        'confidence' => 100.1,
                        'source' => 'ocr',
                    ],
                ],
            )
        );
    }

    public function test_missing_source_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NormalizedExtractionValidator())->validate(
            new NormalizedExtraction(
                schemaVersion: 'ocr-extraction-v1',
                fields: [
                    'supplier' => [
                        'value' => 'Example Pty Ltd',
                        'confidence' => 98.5,
                        'source' => '',
                    ],
                ],
            )
        );
    }

    public function test_non_numeric_confidence_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NormalizedExtractionValidator())->validate(
            new NormalizedExtraction(
                schemaVersion: 'ocr-extraction-v1',
                fields: [
                    'supplier' => [
                        'value' => 'Example Pty Ltd',
                        'confidence' => 'high',
                        'source' => 'ocr',
                    ],
                ],
            )
        );
    }

    public function test_confidence_zero_is_accepted(): void
    {
        $extraction = new NormalizedExtraction(
            schemaVersion: 'ocr-extraction-v1',
            fields: [
                'supplier' => [
                    'value' => 'Example Pty Ltd',
                    'confidence' => 0,
                    'source' => 'ocr',
                ],
            ],
        );

        $validated = (new NormalizedExtractionValidator())
            ->validate($extraction);

        $this->assertSame($extraction, $validated);
    }

    public function test_confidence_one_hundred_is_accepted(): void
    {
        $extraction = new NormalizedExtraction(
            schemaVersion: 'ocr-extraction-v1',
            fields: [
                'supplier' => [
                    'value' => 'Example Pty Ltd',
                    'confidence' => 100,
                    'source' => 'ocr',
                ],
            ],
        );

        $validated = (new NormalizedExtractionValidator())
            ->validate($extraction);

        $this->assertSame($extraction, $validated);
    }

    public function test_empty_field_path_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NormalizedExtractionValidator())->validate(
            new NormalizedExtraction(
                schemaVersion: 'ocr-extraction-v1',
                fields: [
                    '' => [
                        'value' => 'Example Pty Ltd',
                        'confidence' => 98.5,
                        'source' => 'ocr',
                    ],
                ],
            )
        );
    }

    public function test_field_value_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NormalizedExtractionValidator())->validate(
            new NormalizedExtraction(
                schemaVersion: 'ocr-extraction-v1',
                fields: [
                    'supplier' => [
                        'confidence' => 98.5,
                        'source' => 'ocr',
                    ],
                ],
            )
        );
    }

    public function test_field_must_be_an_array(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NormalizedExtractionValidator())->validate(
            new NormalizedExtraction(
                schemaVersion: 'ocr-extraction-v1',
                fields: [
                    'supplier' => 'Example Pty Ltd',
                ],
            )
        );
    }
}