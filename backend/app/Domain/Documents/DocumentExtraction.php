<?php

namespace App\Domain\Documents\Models;

use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Shared\Models\UuidModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentExtraction extends UuidModel
{
    protected $table = 'document_extractions';

    protected $fillable = [
        'document_id',
        'provider',
        'model',
        'model_version',
        'extraction_version',
        'status',
        'correlation_id',
        'started_at',
        'completed_at',
        'output',
        'quality_metadata',
        'error_metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'output' => 'array',
            'quality_metadata' => 'array',
            'error_metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(TaxEvidence::class, 'extraction_id');
    }
}
