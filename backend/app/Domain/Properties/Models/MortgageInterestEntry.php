<?php

namespace App\Domain\Properties\Models;

use App\Domain\Shared\Models\UuidModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MortgageInterestEntry extends UuidModel
{
    protected $table = 'mortgage_interest_entries';

    protected $fillable = [
        'mortgage_id',
        'month',
        'interest_amount',
    ];

    public function mortgage(): BelongsTo
    {
        return $this->belongsTo(Mortgage::class);
    }
}
