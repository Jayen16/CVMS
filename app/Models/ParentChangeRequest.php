<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class ParentChangeRequest extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['facility_id', 'request_uuid', 'child_uuid', 'parent_uuid', 'request_type', 'requested_data', 'status', 'reviewed_by', 'reviewer_name', 'reviewer_note'];

    protected function casts(): array
    {
        return ['requested_data' => 'array'];
    }
}
