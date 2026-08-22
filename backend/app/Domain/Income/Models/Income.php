<?php

namespace App\Domain\Income\Models;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Shared\Models\UuidModel;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $transaction_id
 * @property string $user_id
 * @property string $financial_year_id
 * @property string|null $tax_category_id
 * @property string|null $tax_subcategory_id
 * @property string $amount
 * @property string|null $source_system
 * @property string|null $external_id
 * @property array<string, mixed>|null $metadata
 * @property-read Transaction $transaction
 * @property-read User $user
 * @property-read FinancialYear $financialYear
 */
class Income extends UuidModel
{
    protected $table = 'income';

    protected $fillable = [
        'transaction_id',
        'user_id',
        'financial_year_id',
        'tax_category_id',
        'tax_subcategory_id',
        'amount',
        'source_system',
        'external_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<FinancialYear, $this> */
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }
}
