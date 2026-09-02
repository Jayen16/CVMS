<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class OfflineSyncOutbox extends Model
{
    use UsesUuidPrimaryKey;

    protected $table = 'offline_sync_outbox';

    protected $fillable = [
        'model_type',
        'event_uuid',
        'entity',
        'model_sync_uuid',
        'operation',
        'version',
        'status',
        'payload',
        'queued_at',
        'synced_at',
        'last_error',
        'attempts',
        'last_attempted_at',
        'synchronized_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'queued_at' => 'datetime',
            'synced_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'synchronized_at' => 'datetime',
        ];
    }
}
