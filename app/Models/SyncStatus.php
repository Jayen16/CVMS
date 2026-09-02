<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncStatus extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'scope',
        'state',
        'last_synced_by',
        'last_synced_at',
        'last_processed',
        'last_failed',
        'last_error',
        'last_attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_attempted_at' => 'datetime',
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
