<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildAppointment extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['child_profile_id', 'vaccine_type_id', 'facility_uuid', 'scheduled_for', 'status', 'notes', 'created_by', 'created_by_name', 'created_by_role', 'sync_version'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'sync_version' => 'integer'];
    }

    public function child(): BelongsTo { return $this->belongsTo(ChildProfile::class, 'child_profile_id'); }
    public function vaccineType(): BelongsTo { return $this->belongsTo(VaccineType::class, 'vaccine_type_id'); }
}
