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
    ];

    protected function casts(): array
    {
        return [
            'location_metadata' => 'array',
        ];
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
