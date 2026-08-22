<?php

namespace App\Domain\Tax\Deductibility\ValueObjects;

use App\Domain\Tax\Deductibility\Enums\DeductibilityOutcome;

final readonly class DeductibilityResult
{
    public function __construct(
        public DeductibilityOutcome $outcome,
        public string $reason,
        public ?string $deductiblePercentage = null,
        public ?string $financialYearId = null,
        public ?string $ruleId = null,
        public ?string $source = null,
    ) {}
}
