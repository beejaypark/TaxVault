<?php

namespace App\Domain\Tax\GST\Enums;

enum GstTreatment: string
{
    case Taxable = 'taxable';
    case GstFree = 'gst_free';
    case InputTaxed = 'input_taxed';
}
