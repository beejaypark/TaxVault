<?php

namespace App\Domain\Tax\Apportionment\Services;

use App\Domain\Tax\Apportionment\ValueObjects\ApportionmentResult;
use InvalidArgumentException;

final class ApportionmentCalculator
{
    public function calculate(
        string $amount,
        string $percentage,
    ): ApportionmentResult {
        $this->assertAmount($amount);
        $this->assertPercentage($percentage);

        $total = $this->money($amount);
        $rate = $this->precision($percentage);

        $allocated = bcmul($total, bcdiv($rate, '100', 8), 8);
        $allocated = $this->truncateMoney($allocated);

        $unallocated = bcsub($total, $allocated, 2);
        $unallocated = $this->money($unallocated);

        return new ApportionmentResult(
            total: $total,
            percentage: $rate,
            allocated: $allocated,
            unallocated: $unallocated,
        );
    }

    private function assertAmount(string $amount): void
    {
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $amount)) {
            throw new InvalidArgumentException(
                'Apportionment amount must be a non-negative decimal string.'
            );
        }
    }

    private function assertPercentage(string $percentage): void
    {
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $percentage)) {
            throw new InvalidArgumentException(
                'Apportionment percentage must be a non-negative decimal string.'
            );
        }

        if ((float) $percentage > 100) {
            throw new InvalidArgumentException(
                'Apportionment percentage cannot exceed 100.'
            );
        }
    }

    private function precision(string $amount): string
    {
        return number_format(
            (float) $amount,
            2,
            '.',
            ''
        );
    }

    private function money(string $amount): string
    {
        return number_format(
            (float) $amount,
            2,
            '.',
            ''
        );
    }

    private function truncateMoney(string $amount): string
    {
        return bcdiv($amount, '1', 2);
    }
}
