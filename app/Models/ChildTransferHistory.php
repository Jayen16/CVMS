<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildTransferHistory extends Model
{
    use UsesUuidPrimaryKey;

    protected $table = 'child_transfer_histories';

    protected $fillable = [
        'child_sync_uuid', 'facility_uuid', 'from_barangay_name', 'to_barangay_name', 'municipality_code',
        'transferred_by_uuid', 'transferred_by_name', 'transferred_by_role', 'transferred_at', 'reason', 'sync_version',
    ];

    protected function casts(): array
    {
        return ['transferred_at' => 'datetime', 'sync_version' => 'integer'];
    }

    /** @return BelongsTo<ChildProfile, $this> */
    public function child(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class, 'child_sync_uuid', 'sync_uuid');
    }
}
