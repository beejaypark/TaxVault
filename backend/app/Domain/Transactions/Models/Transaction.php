<?php

namespace App\Domain\Transactions\Models;

use App\Domain\Documents\Models\Document;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $financial_year_id
 * @property string|null $document_id
 * @property Carbon $transaction_date
 * @property Carbon|null $settlement_date
 * @property string|null $description
 * @property string $direction
 * @property string $amount
 * @property string $currency
 * @property string|null $source_system
 * @property string|null $external_transaction_id
 * @property array<string, mixed>|null $provenance
 * @property string|null $tax_category_id
 * @property-read FinancialYear $financialYear
 * @property-read Document|null $document
 * @property-read User $user
 */
class Transaction extends UuidModel
{
    protected $fillable = [
        'user_id',
        'financial_year_id',
        'document_id',
        'transaction_date',
        'settlement_date',
        'description',
        'direction',
        'amount',
        'currency',
        'source_system',
        'external_transaction_id',
        'provenance',
        'tax_category_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'settlement_date' => 'date',
            'amount' => 'decimal:2',
            'provenance' => 'array',
        ];
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

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
