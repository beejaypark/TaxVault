<?php

namespace Tests\Unit\Domain\Tax\GST;

use App\Domain\Tax\GST\Enums\GstTreatment;
use App\Domain\Tax\GST\Services\GstCalculator;
use PHPUnit\Framework\TestCase;

class GstCalculatorTest extends TestCase
{
    private GstCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new GstCalculator();
    }

    public function test_exclusive_taxable_amount_calculates_gst(): void
    {
        $result = $this->calculator->fromExclusive('100.00');

        $this->assertSame('100.00', $result->exclusive);
        $this->assertSame('10.00', $result->gst);
        $this->assertSame('110.00', $result->inclusive);
    }

    public function test_inclusive_taxable_amount_extracts_gst(): void
    {
        $result = $this->calculator->fromInclusive('110.00');

        $this->assertSame('100.00', $result->exclusive);
        $this->assertSame('10.00', $result->gst);
        $this->assertSame('110.00', $result->inclusive);
    }

    public function test_gst_free_has_no_gst(): void
    {
        $result = $this->calculator->fromExclusive(
            '100.00',
            GstTreatment::GstFree,
        );

        $this->assertSame('100.00', $result->exclusive);
        $this->assertSame('0.00', $result->gst);
        $this->assertSame('100.00', $result->inclusive);
    }

    public function test_input_taxed_has_no_gst(): void
    {
        $result = $this->calculator->fromInclusive(
            '100.00',
            GstTreatment::InputTaxed,
        );

        $this->assertSame('100.00', $result->exclusive);
        $this->assertSame('0.00', $result->gst);
        $this->assertSame('100.00', $result->inclusive);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->fromExclusive('-100.00');
    }
}
