<?php

namespace App\Domain\Deductions\Models;

use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkFromHomeLog extends UuidModel
{
    protected $table = 'work_from_home_logs';

    protected $fillable = ['user_id', 'work_date', 'hours', 'note'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
