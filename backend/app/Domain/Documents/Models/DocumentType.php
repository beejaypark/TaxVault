<?php

namespace App\Domain\Documents\Models;

use App\Domain\Shared\Models\UuidModel;

class DocumentType extends UuidModel
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'classification_metadata',
        'status',
        'sort_order',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'classification_metadata' => 'array',
        ];
    }
}
