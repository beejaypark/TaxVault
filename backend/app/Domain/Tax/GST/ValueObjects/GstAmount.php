<?php

namespace App\Domain\Tax\GST\ValueObjects;

final readonly class GstAmount
{
    public function __construct(
        public string $exclusive,
        public string $gst,
        public string $inclusive,
    ) {}
}
