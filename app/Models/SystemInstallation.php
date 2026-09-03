<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemInstallation extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['instance_uuid', 'facility_id', 'facility_code', 'facility_name', 'central_url', 'passport_client_id', 'passport_client_secret', 'status', 'activated_at', 'last_synchronized_at', 'pull_cursor', 'revoked_at'];

    protected $hidden = ['passport_client_secret'];

    protected function casts(): array
    {
        return ['passport_client_secret' => 'encrypted', 'activated_at' => 'datetime', 'last_synchronized_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
