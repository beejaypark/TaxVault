<?php

namespace App\Domain\Taxonomy\Models;

use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Shared\Models\UuidModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxSubcategory extends UuidModel
{
    protected $fillable = [
        'tax_category_id',
        'code',
        'name',
        'sort_order',
        'status',
        'taxonomy_version',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class, 'tax_category_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(TaxEvidence::class);
    }
}
