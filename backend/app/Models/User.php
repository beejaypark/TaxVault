<?php

namespace App\Models;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\Property;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'identity_provider',
        'provider_subject',
        'email',
        'display_name',
        'status',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'deactivated_at' => 'datetime',
        ];
    }

    public function financialYears(): HasMany
    {
        return $this->hasMany(FinancialYear::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
