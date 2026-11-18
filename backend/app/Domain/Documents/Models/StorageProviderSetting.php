<?php

namespace App\Domain\Documents\Models;

use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageProviderSetting extends UuidModel
{
    protected $fillable = ['user_id', 'provider', 'client_id', 'client_secret', 'tenant'];

    protected function casts(): array
    {
        return [
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
        ];
    }

    protected $hidden = ['client_id', 'client_secret'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
