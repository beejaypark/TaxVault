<?php

namespace App\Domain\Documents\Models;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Documents\Models\DocumentExtraction;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Documents\Models\DocumentType;
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
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'captured_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'provenance' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            $immutableAttributes = [
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
            ];

            $changedImmutableAttributes = array_intersect(
                $immutableAttributes,
                array_keys($document->getDirty())
            );

            if ($changedImmutableAttributes !== []) {
                throw new LogicException(
                    'Document evidence metadata is immutable.'
                );
            }
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Documents cannot be deleted.'
            );
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

}
