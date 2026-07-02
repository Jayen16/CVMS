<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class VaccineSchedule extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'vaccine_type_id',
        'vaccine_schedule_version_id',
        'dose_number',
        'age_days',
        'age_weeks',
        'age_months',
        'age_years',
        'label',
        'indication',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<VaccineType, $this>
     */
    public function vaccineType(): BelongsTo
    {
        return $this->belongsTo(VaccineType::class);
    }

    /**
     * @return BelongsTo<VaccineScheduleVersion, $this>
     */
    public function scheduleVersion(): BelongsTo
    {
        return $this->belongsTo(VaccineScheduleVersion::class, 'vaccine_schedule_version_id');
    }

    public function dueDateFromBirthdate(Carbon $birthdate): Carbon
    {
        return $birthdate->copy()
            ->addDays((int) $this->age_days)
            ->addWeeks((int) $this->age_weeks)
            ->addMonthsNoOverflow((int) $this->age_months)
            ->addYearsNoOverflow((int) $this->age_years);
    }

    public function ageSummary(): string
    {
        $parts = [];

        foreach ([
            'years' => $this->age_years,
            'months' => $this->age_months,
            'weeks' => $this->age_weeks,
            'days' => $this->age_days,
        ] as $unit => $value) {
            if ((int) $value > 0) {
                $parts[] = $value.' '.$unit;
            }
        }

        return $parts === [] ? 'At birth' : implode(', ', $parts);
    }

    /**
     * @return array<string, string>
     */
    public static function indicationOptions(): array
    {
        return [
            'routine_vaccination' => 'Routine vaccination',
            'catch_up_vaccination' => 'Catch-up vaccination',
            'special_groups' => 'Recommended vaccination for special groups/situations',
            'national_immunization_program' => 'National Immunization Program (NIP)',
            'nip_pidsp_catch_up' => 'NIP/PIDSP catch-up',
            'recommended_by_nip_pidsp_pfv' => 'Recommended by NIP and PPS/PIDSP/PFV',
        ];
    }

    public function indicationLabel(): string
    {
        return self::indicationOptions()[$this->indication] ?? 'Routine vaccination';
    }

    public function indicationClass(): string
    {
        return 'schedule-indication-'.str_replace('_', '-', $this->indication ?: 'routine_vaccination');
    }
}
