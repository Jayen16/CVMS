<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class FacilityGuardian extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['facility_id', 'guardian_uuid', 'name', 'email', 'phone', 'active', 'sync_version'];
}
