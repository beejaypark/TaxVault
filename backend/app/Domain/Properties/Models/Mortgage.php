<?php

namespace App\Domain\Properties\Models;

use App\Domain\Shared\Models\UuidModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mortgage extends UuidModel
{
    protected $table = 'mortgages';

    protected $fillable = [
        'user_id',
        'property_id',
        'bank_name',
        'loan_type',
        'loan_amount',
        'interest_rate',
        'start_date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function interestEntries(): HasMany
    {
        return $this->hasMany(MortgageInterestEntry::class)->orderBy('month');
    }
}
