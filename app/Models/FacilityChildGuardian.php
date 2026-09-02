<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class FacilityChildGuardian extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['facility_id', 'child_uuid', 'guardian_uuid', 'relationship', 'sync_version'];
}
