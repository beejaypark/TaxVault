<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InboundEmailAddress extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'token'];

    public static function forUser(User $user): self
    {
        return self::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['token' => Str::lower(Str::random(24))],
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): string
    {
        return $this->token.'@'.config('services.inbound_email.domain');
    }
}
