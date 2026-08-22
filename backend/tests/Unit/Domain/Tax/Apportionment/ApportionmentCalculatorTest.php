<?php

namespace Tests\Unit\Domain\Tax\Apportionment;

use App\Domain\Tax\Apportionment\Services\ApportionmentCalculator;
use PHPUnit\Framework\TestCase;

class ApportionmentCalculatorTest extends TestCase
{
    private ApportionmentCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ApportionmentCalculator();
    }

    public function test_it_apportions_amount_by_percentage(): void
    {
        $result = $this->calculator->calculate('100.00', '60.00');

        $this->assertSame('100.00', $result->total);
        $this->assertSame('60.00', $result->percentage);
        $this->assertSame('60.00', $result->allocated);
        $this->assertSame('40.00', $result->unallocated);
    }

    public function test_zero_percentage_allocates_nothing(): void
    {
        $result = $this->calculator->calculate('100.00', '0.00');

        $this->assertSame('0.00', $result->allocated);
        $this->assertSame('100.00', $result->unallocated);
    }

    public function test_one_hundred_percentage_allocates_everything(): void
    {
        $result = $this->calculator->calculate('100.00', '100.00');

        $this->assertSame('100.00', $result->allocated);
        $this->assertSame('0.00', $result->unallocated);
    }

    public function test_fractional_percentage_is_supported(): void
    {
        $result = $this->calculator->calculate('123.45', '33.33');

        $this->assertSame('41.14', $result->allocated);
        $this->assertSame('82.31', $result->unallocated);
    }

    public function test_percentage_above_one_hundred_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate('100.00', '100.01');
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate('-100.00', '50.00');
    }

    public function test_invalid_percentage_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate('100.00', 'abc');
    }

    public function test_result_is_rounded_to_two_decimal_places(): void
    {
        $result = $this->calculator->calculate('100.00', '33.3333');

        $this->assertSame('33.33', $result->percentage);
        $this->assertSame('33.33', $result->allocated);
        $this->assertSame('66.67', $result->unallocated);
    }
}
