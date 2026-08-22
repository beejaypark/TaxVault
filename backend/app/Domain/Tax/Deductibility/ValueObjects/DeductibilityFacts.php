<?php

namespace App\Domain\Tax\Deductibility\ValueObjects;

use InvalidArgumentException;

final readonly class DeductibilityFacts
{
    public function __construct(
        public bool $businessPurpose,
        public bool $privatePurpose = false,
        public ?string $businessUsePercentage = null,
        public bool $capitalAsset = false,
        public bool $immediateDeductionEligible = false,
    ) {
        if ($businessPurpose && $privatePurpose) {
            throw new InvalidArgumentException(
                'Business and private purpose cannot both be exclusively true.'
            );
        }

        if ($businessUsePercentage !== null) {
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $businessUsePercentage)) {
                throw new InvalidArgumentException(
                    'Business use percentage must be a non-negative decimal string.'
                );
            }

            if ((float) $businessUsePercentage > 100) {
                throw new InvalidArgumentException(
                    'Business use percentage cannot exceed 100.'
                );
            }
        }
    }
}
