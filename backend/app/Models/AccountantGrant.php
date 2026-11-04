<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountantGrant extends Model
{
    use HasUuids;

    public const SECTIONS = ['documents', 'transactions', 'reviews', 'assets'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'owner_id',
        'accountant_user_id',
        'name',
        'email',
        'firm',
        'status',
        'financial_year_ids',
        'sections',
        'last_invited_at',
        'last_pack_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'financial_year_ids' => 'array',
            'sections' => 'array',
            'last_invited_at' => 'datetime',
            'last_pack_sent_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function accountant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_user_id');
    }

    public function allows(string $section): bool
    {
        return in_array($section, $this->sections ?? [], true);
    }
}
