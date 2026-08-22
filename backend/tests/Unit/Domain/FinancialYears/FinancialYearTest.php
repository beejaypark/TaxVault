<?php

namespace Tests\Unit\Domain\FinancialYears;

use App\Domain\FinancialYears\Models\FinancialYear;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialYearTest extends TestCase
{
    #[Test]
    public function it_accepts_a_canonical_australian_financial_year(): void
    {
        FinancialYear::assertCanonicalAustralianPeriod(
            '2025-26',
            CarbonImmutable::parse('2025-07-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_non_canonical_financial_year_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FinancialYear::assertCanonicalAustralianPeriod(
            '2025-26',
            CarbonImmutable::parse('2025-07-01'),
            CarbonImmutable::parse('2026-07-01'),
        );
    }
}
