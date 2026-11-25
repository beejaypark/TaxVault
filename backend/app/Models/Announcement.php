<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'created_by',
        'title',
        'message',
        'theme',
        'icon',
        'font_family',
        'is_bold',
        'is_dismissible',
        'action_label',
        'action_url',
        'is_published',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_bold' => 'boolean',
            'is_dismissible' => 'boolean',
            'is_published' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
