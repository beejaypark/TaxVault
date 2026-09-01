<?php

namespace Tests\Unit\Application\OCR;

use App\Application\OCR\Confidence\ConfidenceEvaluator;
use App\Application\OCR\Confidence\ConfidencePolicy;
use App\Application\OCR\Data\NormalizedExtraction;
use PHPUnit\Framework\TestCase;

class ConfidenceEvaluatorTest extends TestCase
{
    public function test_high_confidence_field_passes(): void
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

        $result = (new ConfidenceEvaluator())->evaluate($extraction);

        $this->assertSame('pass', $result->status);
        $this->assertFalse($result->requiresReview());
        $this->assertSame([], $result->reviewFields);
        $this->assertSame('pass', $result->fields['supplier']['status']);
    }

    public function test_low_confidence_field_requires_review(): void
    {
        $extraction = new NormalizedExtraction(
            schemaVersion: 'ocr-extraction-v1',
            fields: [
                'amount' => [
                    'value' => 110.00,
                    'confidence' => 72.0,
                    'source' => 'ocr',
                ],
            ],
        );

        $result = (new ConfidenceEvaluator())->evaluate($extraction);

        $this->assertSame('review', $result->status);
        $this->assertTrue($result->requiresReview());
        $this->assertSame(['amount'], $result->reviewFields);
        $this->assertSame('review', $result->fields['amount']['status']);
        $this->assertSame(90.0, $result->fields['amount']['threshold']);
    }

    public function test_threshold_boundary_passes(): void
    {
        $extraction = new NormalizedExtraction(
            schemaVersion: 'ocr-extraction-v1',
            fields: [
                'amount' => [
                    'value' => 110.00,
                    'confidence' => 90.0,
                    'source' => 'ocr',
                ],
            ],
        );

        $result = (new ConfidenceEvaluator())->evaluate($extraction);

        $this->assertSame('pass', $result->status);
        $this->assertSame([], $result->reviewFields);
    }

    public function test_critical_field_uses_critical_threshold(): void
    {
        $policy = new ConfidencePolicy(
            defaultThreshold: 90.0,
            criticalThreshold: 95.0,
            criticalFields: ['amount'],
        );

        $extraction = new NormalizedExtraction(
            schemaVersion: 'ocr-extraction-v1',
            fields: [
                'supplier' => [
                    'value' => 'Example Pty Ltd',
                    'confidence' => 92.0,
                    'source' => 'ocr',
                ],
                'amount' => [
                    'value' => 110.00,
                    'confidence' => 92.0,
                    'source' => 'ocr',
                ],
            ],
        );

        $result = (new ConfidenceEvaluator($policy))->evaluate($extraction);

        $this->assertSame('review', $result->status);
        $this->assertSame(
            ['amount'],
            $result->reviewFields
        );
        $this->assertSame(
            90.0,
            $result->fields['supplier']['threshold']
        );
        $this->assertSame(
            95.0,
            $result->fields['amount']['threshold']
        );
        $this->assertTrue(
            $result->fields['amount']['critical']
        );
    }

    public function test_multiple_low_confidence_fields_are_collected(): void
    {
        $extraction = new NormalizedExtraction(
            schemaVersion: 'ocr-extraction-v1',
            fields: [
                'supplier' => [
                    'value' => 'Example Pty Ltd',
                    'confidence' => 98.0,
                    'source' => 'ocr',
                ],
                'amount' => [
                    'value' => 110.00,
                    'confidence' => 70.0,
                    'source' => 'ocr',
                ],
                'invoice_number' => [
                    'value' => 'INV-001',
                    'confidence' => 80.0,
                    'source' => 'ocr',
                ],
            ],
        );

        $result = (new ConfidenceEvaluator())->evaluate($extraction);

        $this->assertSame(
            ['amount', 'invoice_number'],
            $result->reviewFields
        );
    }
}
