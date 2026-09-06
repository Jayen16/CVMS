<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class SyncReceivedItem extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'entity',
        'record_uuid',
        'operation',
        'payload',
        'last_received_batch_uuid',
        'first_received_at',
        'last_received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'first_received_at' => 'datetime',
            'last_received_at' => 'datetime',
        ];
    }
}
