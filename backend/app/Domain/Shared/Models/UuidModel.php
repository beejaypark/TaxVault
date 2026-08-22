<?php

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

abstract class UuidModel extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}
