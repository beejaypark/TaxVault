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
        'bank_repayment_amount',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:3',
            'bank_repayment_amount' => 'decimal:2',
            'effective_date' => 'date:Y-m-d',
        ];
    }

    public function mortgage(): BelongsTo
    {
        return $this->belongsTo(Mortgage::class);
    }
}
