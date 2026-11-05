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

    protected $with = ['interestEntries', 'rateChanges'];

    public function rateChanges(): HasMany
    {
        return $this->hasMany(MortgageRateChange::class)->orderBy('effective_date');
    }

    /** Keeps the headline rate equal to the latest dated change that has already taken effect. */
    public function syncCurrentRate(): void
    {
        $current = $this->rateChanges()->where('effective_date', '<=', now()->toDateString())->reorder('effective_date', 'desc')->first();

        if ($current !== null) {
            $this->update(['interest_rate' => $current->rate]);
        }
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function interestEntries(): HasMany
    {
        return $this->hasMany(MortgageInterestEntry::class)->orderBy('month');
    }
}
