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
        'client_submission_id',
        'sync_uuid',
        'next_due_at',
        'suggested_vaccine',
        'suggestion_note',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'date',
            'next_due_at' => 'date',
            'verified_at' => 'datetime',
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

    protected static function booted(): void
    {
        static::creating(function (VaccinationRecord $record): void {
            if (blank($record->sync_uuid)) {
                $record->sync_uuid = (string) Str::uuid();
            }
        });
    }
}
