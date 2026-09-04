<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Barangay extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'name',
        'municipality',
        'municipality_id',
    ];

    public function municipalityRelation(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function nurses(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasManyRelation<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<ChildProfile, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ChildProfile::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /** @return HasManyThrough<VaccinationRecord, ChildProfile, $this> */
    public function vaccinations(): HasManyThrough
    {
        return $this->hasManyThrough(VaccinationRecord::class, ChildProfile::class);
    }

    /**
     * @return HasMany<ClinicAnnouncement, $this>
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(ClinicAnnouncement::class);
    }

    /**
     * @return HasMany<VaccineInventoryTransaction, $this>
     */
    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(VaccineInventoryTransaction::class);
    }
}
