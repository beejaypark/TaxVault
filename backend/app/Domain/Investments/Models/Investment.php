<?php

namespace App\Domain\Investments\Models;

use App\Domain\Properties\Models\Property;
use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investment extends UuidModel
{
    protected $table = 'investments';

    protected $fillable = [
        'user_id',
        'property_id',
        'investment_type',
        'acquisition_date',
        'disposal_date',
        'quantity',
        'ownership_percentage',
        'cost_base',
        'incidental_costs',
        'proceeds',
        'source_system',
        'external_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'disposal_date' => 'date',
            'quantity' => 'decimal:8',
            'ownership_percentage' => 'decimal:4',
            'cost_base' => 'decimal:2',
            'incidental_costs' => 'decimal:2',
            'proceeds' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
