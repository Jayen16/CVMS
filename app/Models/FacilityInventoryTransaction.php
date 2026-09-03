<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class FacilityInventoryTransaction extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['facility_id', 'transaction_uuid', 'barangay_name', 'vaccine_code', 'item_code', 'batch_number', 'expiry_date', 'transaction_type', 'movement', 'quantity', 'transaction_date', 'reference_number', 'recorded_by_uuid', 'recorded_by_name', 'recorded_by_role', 'notes', 'sync_version'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'transaction_date' => 'date', 'sync_version' => 'integer'];
    }
}
