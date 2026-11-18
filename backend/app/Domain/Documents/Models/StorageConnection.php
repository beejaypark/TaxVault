<?php

namespace App\Domain\Documents\Models;

use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageConnection extends UuidModel
{
    protected $fillable = [
        'user_id',
        'provider',
        'external_account_id',
        'display_name',
        'status',
        'metadata',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'connected_at',
        'last_synced_at',
        'last_error_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
