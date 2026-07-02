<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaccineScheduleVersion extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'name',
        'version_code',
        'effective_date',
        'status',
        'source',
        'source_url',
        'notes',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<VaccineSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(VaccineSchedule::class);
    }

    /**
     * @return HasMany<ChildVaccineSeriesVersion, $this>
     */
    public function seriesAssignments(): HasMany
    {
        return $this->hasMany(ChildVaccineSeriesVersion::class);
    }
}
