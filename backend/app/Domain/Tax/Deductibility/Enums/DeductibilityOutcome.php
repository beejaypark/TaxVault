<?php

namespace App\Domain\Tax\Deductibility\Enums;

enum DeductibilityOutcome: string
{
    case Deductible = 'deductible';
    case NonDeductible = 'non_deductible';
    case Partial = 'partial';
    case Capital = 'capital';
    case ReviewRequired = 'review_required';
}
