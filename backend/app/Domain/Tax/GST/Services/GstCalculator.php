<?php

namespace App\Domain\Tax\GST\Services;

use App\Domain\Tax\GST\Enums\GstTreatment;
use App\Domain\Tax\GST\ValueObjects\GstAmount;
use InvalidArgumentException;

final class GstCalculator
{
    private const RATE = '0.10';

    public function fromExclusive(
        string $amount,
        GstTreatment $treatment = GstTreatment::Taxable,
    ): GstAmount {
        $this->assertAmount($amount);

        if ($treatment !== GstTreatment::Taxable) {
            return new GstAmount(
                exclusive: $this->money($amount),
                gst: '0.00',
                inclusive: $this->money($amount),
            );
        }

        $gst = bcmul($amount, self::RATE, 4);
        $gst = $this->money($gst);

        return new GstAmount(
            exclusive: $this->money($amount),
            gst: $gst,
            inclusive: $this->money(bcadd($amount, $gst, 4)),
        );
    }

    public function fromInclusive(
        string $amount,
        GstTreatment $treatment = GstTreatment::Taxable,
    ): GstAmount {
        $this->assertAmount($amount);

        if ($treatment !== GstTreatment::Taxable) {
            return new GstAmount(
                exclusive: $this->money($amount),
                gst: '0.00',
                inclusive: $this->money($amount),
            );
        }

        $exclusive = bcdiv($amount, '1.10', 4);
        $exclusive = $this->money($exclusive);

        $gst = $this->money(
            bcsub($amount, $exclusive, 4)
        );

        return new GstAmount(
            exclusive: $exclusive,
            gst: $gst,
            inclusive: $this->money($amount),
        );
    }

    private function assertAmount(string $amount): void
    {
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $amount)) {
            throw new InvalidArgumentException(
                'GST amount must be a non-negative decimal string.'
            );
        }
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
}
