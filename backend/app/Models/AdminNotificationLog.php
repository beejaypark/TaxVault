<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminNotificationLog extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['user_id', 'recipient', 'notification_type', 'status', 'error', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
}
