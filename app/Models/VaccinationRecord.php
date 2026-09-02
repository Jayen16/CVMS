<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VaccinationRecord extends Model
{
    use Archivable, UsesUuidPrimaryKey;

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
        'facility_uuid',
        'administered_by_uuid',
        'recorded_by_uuid',
        'administered_by_name',
        'recorded_by_name',
        'recorded_by_role',
        'sync_version',
        'next_due_at',
        'suggested_vaccine',
        'suggested_schedule_version_id',
        'suggestion_note',
        'remarks',
        'archived_at',
        'archived_by',
        'archive_reason',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'date',
            'next_due_at' => 'date',
            'verified_at' => 'datetime',
            'proof_paths' => 'array',
            'archived_at' => 'datetime',
            'sync_version' => 'integer',
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

    public function recordedByDisplayName(): string
    {
        $uuid = $this->recorded_by_uuid ?: $this->recorded_by;
        $staff = $uuid ? FacilityStaff::query()->where('staff_uuid', $uuid)->when($this->facility_uuid, fn ($query) => $query->where('facility_id', $this->facility_uuid))->value('name') : null;

        return $staff ?: $this->recorded_by_name ?: $this->recorder?->name ?: 'Unknown staff';
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

    /** @return HasMany<VaccineInventoryTransaction, $this> */
    public function inventoryUsageTransactions(): HasMany
    {
        return $this->hasMany(VaccineInventoryTransaction::class, 'vaccination_record_id');
    }

    public function isPendingVerification(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isParentEditable(): bool
    {
        if (! $this->isPendingVerification()) {
            return false;
        }

        return ! \App\Models\OfflineSyncOutbox::query()
            ->where('entity', 'immunization_records')
            ->where('model_sync_uuid', $this->sync_uuid)
            ->whereNotNull('synced_at')
            ->exists();
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

        static::updating(function (VaccinationRecord $record): void {
            if ($record->isDirty(array_diff($record->getDirty(), ['sync_version']))) {
                $record->sync_version = (int) ($record->getRawOriginal('sync_version') ?: 1) + 1;
            }
        });
    }
}
