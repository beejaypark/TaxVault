<?php

namespace App\Domain\Evidence\Models;

use App\Domain\Documents\Models\Document;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Taxonomy\Models\TaxCategory;
use App\Domain\Taxonomy\Models\TaxSubcategory;
use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxEvidence extends UuidModel
{
    protected $table = 'tax_evidences';

    protected $fillable = [
        'user_id',
        'financial_year_id',
        'document_id',
        'extraction_id',
        'evidence_type',
        'source_type',
        'source_id',
        'field_path',
        'extracted_value',
        'tax_category_id',
        'tax_subcategory_id',
        'classification_reason',
        'status',
        'verification_status',
        'confidence',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'verified_at' => 'datetime',
            'extracted_value' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(DocumentExtraction::class);
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function taxSubcategory(): BelongsTo
    {
        return $this->belongsTo(TaxSubcategory::class);
    }
}
