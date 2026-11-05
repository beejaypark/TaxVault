<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BankConnection extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'last_synced_at'];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime'];
    }
}
