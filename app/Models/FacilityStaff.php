<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class FacilityStaff extends Model
{
    use UsesUuidPrimaryKey;

    protected $table = 'facility_staff';

    protected $fillable = ['facility_id', 'staff_uuid', 'name', 'role', 'active', 'last_seen_at'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_seen_at' => 'datetime'];
    }
}
