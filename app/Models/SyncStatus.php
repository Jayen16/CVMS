<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncStatus extends Model
{
    protected $fillable = [
        'scope',
        'last_synced_by',
        'last_synced_at',
        'last_processed',
        'last_failed',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_synced_by');
    }
}
