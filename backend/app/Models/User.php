<?php

namespace App\Models;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\Property;
use App\Domain\Documents\Models\StorageConnection;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Documents\Models\StorageProviderSetting;
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
        'role',
        'deactivated_at',
        'preferences',
        'ai_settings',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    public function isAccountant(): bool
    {
        return $this->role === 'accountant';
    }

    public function preference(string $key): mixed
    {
        return ($this->preferences ?? [])[$key] ?? null;
    }

    public function accountantGrants(): HasMany
    {
        return $this->hasMany(AccountantGrant::class, 'accountant_user_id');
    }

    protected function casts(): array
    {
        return [
            'deactivated_at' => 'datetime',
            'preferences' => 'array',
            'ai_settings' => 'encrypted:array',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
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

    public function storageConnections(): HasMany
    {
        return $this->hasMany(StorageConnection::class);
    }

    public function storageProviderSettings(): HasMany
    {
        return $this->hasMany(StorageProviderSetting::class);
    }
}
