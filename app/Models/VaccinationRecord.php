<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VaccinationRecord extends Model
{
    protected $fillable = [
        'child_profile_id',
        'vaccine_type_id',
        'recorded_by',
        'submitted_by',
        'verified_by',
        'dose_number',
        'source',
        'verification_status',
        'administered_at',
        'verified_at',
        'clinic_name',
        'clinic_location',
        'proof_path',
        'proof_paths',
        'client_submission_id',
        'sync_uuid',
        'next_due_at',
        'suggested_vaccine',
        'suggested_schedule_version_id',
        'suggestion_note',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'date',
            'next_due_at' => 'date',
            'verified_at' => 'datetime',
            'proof_paths' => 'array',
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
     * @return BelongsTo<VaccineType, $this>
     */
    public function vaccineType(): BelongsTo
    {
        return $this->belongsTo(VaccineType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<VaccineScheduleVersion, $this>
     */
    public function suggestedScheduleVersion(): BelongsTo
    {
        return $this->belongsTo(VaccineScheduleVersion::class, 'suggested_schedule_version_id');
    }

    /**
     * @return HasMany<AdverseEventReport, $this>
     */
    public function adverseEventReports(): HasMany
    {
        return $this->hasMany(AdverseEventReport::class);
    }

    public function isPendingVerification(): bool
    {
        return $this->verification_status === 'pending';
    }

    /**
     * @return list<string>
     */
    public function proofPaths(): array
    {
        $paths = $this->proof_paths ?? [];

        if ($this->proof_path !== null && ! in_array($this->proof_path, $paths, true)) {
            $paths[] = $this->proof_path;
        }

        return array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
    }

    protected static function booted(): void
    {
        static::creating(function (VaccinationRecord $record): void {
            if (blank($record->sync_uuid)) {
                $record->sync_uuid = (string) Str::uuid();
            }
        });
    }
}
