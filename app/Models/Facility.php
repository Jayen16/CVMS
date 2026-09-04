<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Facility extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['code', 'name', 'barangay_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function activationCodes(): HasMany
    {
        return $this->hasMany(FacilityActivationCode::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(FacilityConnection::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
