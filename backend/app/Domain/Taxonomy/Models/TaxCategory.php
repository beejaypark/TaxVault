<?php

namespace App\Domain\Taxonomy\Models;

use App\Domain\Shared\Models\UuidModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Evidence\Models\TaxEvidence;

class TaxCategory extends UuidModel
{
    protected $fillable = ['code', 'name', 'sort_order', 'status', 'taxonomy_version'];

    public function subcategories(): HasMany
    {
        return $this->hasMany(TaxSubcategory::class);
    }
    public function evidences(): HasMany
    {
        return $this->hasMany(TaxEvidence::class);
    }


}
