<?php

namespace App\Domain\TaxNews\Models;

use App\Domain\Shared\Models\UuidModel;

class TaxNews extends UuidModel
{
    protected $table = 'tax_news';

    protected $fillable = [
        'source', 'external_id', 'title', 'summary', 'url', 'category', 'importance', 'status', 'published_at', 'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'effective_at' => 'datetime',
        ];
    }
}
