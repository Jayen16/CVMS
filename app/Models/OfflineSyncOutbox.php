<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineSyncOutbox extends Model
{
    protected $table = 'offline_sync_outbox';

    protected $fillable = [
        'model_type',
        'model_sync_uuid',
        'operation',
        'payload',
        'queued_at',
        'synced_at',
        'last_error',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'queued_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
