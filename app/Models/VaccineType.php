<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaccineType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<VaccinationRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class);
    }

    /**
     * @return HasMany<VaccineSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(VaccineSchedule::class);
    }
}
