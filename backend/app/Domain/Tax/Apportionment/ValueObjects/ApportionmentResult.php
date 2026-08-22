<?php

namespace App\Domain\Tax\Apportionment\ValueObjects;

use InvalidArgumentException;

final readonly class ApportionmentResult
{
    public function __construct(
        public string $total,
        public string $percentage,
        public string $allocated,
        public string $unallocated,
    ) {}
}
