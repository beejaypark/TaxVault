<?php

namespace App\Domain\Income\Models;

use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringIncomeRule extends UuidModel
{
    protected $table = 'recurring_income_rules';

    protected $fillable = [
        'user_id', 'name', 'description', 'amount', 'day_of_month',
        'start_date', 'end_date', 'ato_category', 'active',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'day_of_month' => 'integer', 'start_date' => 'date', 'end_date' => 'date', 'active' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
