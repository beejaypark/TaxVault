<?php

namespace App\Domain\Documents\Models;

use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Document extends UuidModel
{
    protected $fillable = [
        'user_id',
        'financial_year_id',
        'document_type_id',
        'storage_disk',
        'object_key',
        'content_sha256',
        'original_filename',
        'mime_type',
        'size_bytes',
        'captured_at',
        'uploaded_at',
        'provenance',
        'status',
        'payment_status',
        'payment_date',
        'payment_method',
        'payment_amount',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'captured_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'provenance' => 'array',
            'payment_date' => 'date:Y-m-d',
            'payment_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            $immutableAttributes = [
                'user_id',
                'document_type_id',
                'storage_disk',
                'object_key',
                'content_sha256',
                'original_filename',
                'mime_type',
                'size_bytes',
                'captured_at',
                'uploaded_at',
                'provenance',
            ];

            $changedImmutableAttributes = array_intersect(
                $immutableAttributes,
                array_keys($document->getDirty())
            );

            $financialYearChanged = $document->isDirty('financial_year_id');
            $financialYearWasAlreadyAssigned = $document->getOriginal('financial_year_id') !== null;

            if (
                $changedImmutableAttributes !== []
                || ($financialYearChanged && $financialYearWasAlreadyAssigned)
                || ($financialYearChanged && $document->financial_year_id === null)
            ) {
                throw new LogicException(
                    'Document evidence metadata is immutable except for assigning a financial year once.'
                );
            }
        });

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(TaxEvidence::class);
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(DocumentExtraction::class);
    }

    public function latestExtraction(): HasOne
    {
        return $this->hasOne(DocumentExtraction::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
