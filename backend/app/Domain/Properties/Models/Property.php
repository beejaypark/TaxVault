<?php

namespace App\Domain\Properties\Models;

use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends UuidModel
{
    protected $table = 'properties';

    protected $fillable = [
        'user_id',
        'reference_code',
        'address_line_1',
        'address_line_2',
        'suburb',
        'state',
        'postcode',
        'country_code',
        'location_metadata',
        'current_value',
        'current_value_date',
    ];

    protected function casts(): array
    {
        return [
            'location_metadata' => 'array',
        ];
    }

    public function mortgages(): HasMany
    {
        return $this->hasMany(Mortgage::class)->with('interestEntries')->orderBy('created_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(PropertyPeriod::class);
    }
}
