<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdverseEventReport extends Model
{
    protected $fillable = [
        'child_profile_id',
        'vaccination_record_id',
        'vaccine_type_id',
        'reported_by',
        'event_date',
        'severity',
        'outcome',
        'symptoms',
        'notes',
        'sync_uuid',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ChildProfile, $this>
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class, 'child_profile_id');
    }

    /**
     * @return BelongsTo<VaccinationRecord, $this>
     */
    public function vaccinationRecord(): BelongsTo
    {
        return $this->belongsTo(VaccinationRecord::class);
    }

    /**
     * @return BelongsTo<VaccineType, $this>
     */
    public function vaccineType(): BelongsTo
    {
        return $this->belongsTo(VaccineType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    protected static function booted(): void
    {
        static::creating(function (AdverseEventReport $report): void {
            if (blank($report->sync_uuid)) {
                $report->sync_uuid = (string) Str::uuid();
            }
        });
    }
}
