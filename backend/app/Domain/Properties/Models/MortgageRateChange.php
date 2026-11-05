<?php

namespace App\Domain\Properties\Models;

use App\Domain\Shared\Models\UuidModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MortgageRateChange extends UuidModel
{
    protected $table = 'mortgage_rate_changes';

    protected $fillable = [
        'mortgage_id',
        'effective_date',
        'rate',
    ];

    public function mortgage(): BelongsTo
    {
        return $this->belongsTo(Mortgage::class);
    }
}
