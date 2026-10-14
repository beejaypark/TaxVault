<?php

namespace App\Domain\Reviews\Models;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Shared\Models\UuidModel;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends UuidModel
{
    protected $table = 'reviews';

    protected $fillable = [
        'reviewer_id',
        'document_id',
        'document_extraction_id',
        'transaction_id',
        'review_type',
        'status',
        'decision',
        'reviewer_notes',
        'supersedes_review_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(
            DocumentExtraction::class,
            'document_extraction_id'
        );
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function supersededReview(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'supersedes_review_id'
        );
    }

    /**
     * Active authoritative corrections created during review.
     *
     * The original DocumentExtraction remains immutable.
     * Corrections are represented separately as TaxEvidence.
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(
            TaxEvidence::class,
            'extraction_id',
            'document_extraction_id'
        )
            ->where(
                'evidence_type',
                'review_correction'
            )
            ->where(
                'status',
                'active'
            )
            ->orderBy('field_path');
    }
}
