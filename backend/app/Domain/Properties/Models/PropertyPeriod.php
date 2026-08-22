<?php

namespace App\Domain\Properties\Models;

use App\Domain\Shared\Models\UuidModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPeriod extends UuidModel
{
    protected $table = 'property_periods';

    protected $fillable = [
        'property_id',
        'period_start',
        'period_end',
        'use_type',
        'ownership_percentage',
        'provenance',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'ownership_percentage' => 'decimal:4',
            'provenance' => 'array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
