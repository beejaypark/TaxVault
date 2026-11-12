<?php

namespace App\Domain\Properties\Models;

use App\Domain\Documents\Models\Document;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyDeduction extends UuidModel
{
    protected $table = 'property_deductions';

    protected $fillable = [
        'user_id', 'property_id', 'financial_year_id', 'source_document_id',
        'deduction_type', 'amount', 'notes',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function financialYear(): BelongsTo { return $this->belongsTo(FinancialYear::class); }
    public function sourceDocument(): BelongsTo { return $this->belongsTo(Document::class, 'source_document_id'); }
}
