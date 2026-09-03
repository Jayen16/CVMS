<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityConnection extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['facility_id', 'instance_uuid', 'instance_name', 'passport_client_id', 'status', 'activated_at', 'last_synchronized_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'last_synchronized_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
