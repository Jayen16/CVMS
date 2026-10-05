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
        'proof_uploaders',
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
        'nurse_remarks',
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
            'proof_uploaders' => 'array',
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

    public function nurseReviewRemarks(): ?string
    {
        if (filled($this->nurse_remarks)) {
            return $this->nurse_remarks;
        }

        // Older records stored the nurse's review text in remarks.
        return $this->verification_status === 'pending' ? null : $this->remarks;
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

    /** @return list<string> */
    public function proofUploaderLabels(): array
    {
        $labels = array_values(array_filter($this->proof_uploaders ?? [], fn ($label) => is_string($label) && $label !== ''));
        $paths = $this->proofPaths();

        if (count($labels) < count($paths)) {
            $fallback = $this->submitter?->name ? 'Parent · '.$this->submitter->name : 'Parent';
            $labels = array_pad($labels, count($paths), $fallback);
        }

        return array_slice($labels, 0, count($paths));
    }

    public function proofUploaderSummary(): string
    {
        return collect($this->proofUploaderLabels())->unique()->implode(' · ');
    }

    public function parentProofUploaderSummary(): string
    {
        return collect($this->proofUploaderLabels())
            ->reject(fn (string $label): bool => str_starts_with($label, 'Nurse ·'))
            ->unique()
            ->implode(' · ');
    }

    public function nurseProofUploaderSummary(): string
    {
        return collect($this->proofUploaderLabels())
            ->filter(fn (string $label): bool => str_starts_with($label, 'Nurse ·'))
            ->unique()
            ->implode(' · ');
    }

    public function nurseProofCount(): int
    {
        return collect($this->proofUploaderLabels())
            ->filter(fn (string $label): bool => str_starts_with($label, 'Nurse ·'))
            ->count();
    }

    /** @return list<int> */
    public function parentProofIndexes(): array
    {
        return collect($this->proofUploaderLabels())
            ->values()
            ->filter(fn (string $label): bool => ! str_starts_with($label, 'Nurse ·'))
            ->keys()
            ->map(fn (int|string $index): int => (int) $index)
            ->all();
    }

    /** @return list<int> */
    public function nurseProofIndexes(): array
    {
        return collect($this->proofUploaderLabels())
            ->values()
            ->filter(fn (string $label): bool => str_starts_with($label, 'Nurse ·'))
            ->keys()
            ->map(fn (int|string $index): int => (int) $index)
            ->all();
    }

    protected static function booted(): void
    {
        static::creating(function (VaccinationRecord $record): void {
            if (blank($record->sync_uuid)) {
                $record->sync_uuid = (string) Str::uuid();
            }
        });

        static::updating(function (VaccinationRecord $record): void {
            // getDirty() is keyed by attribute name. Using its values here
            // meant verification/rejection changes did not increment the
            // sync version, so the outbound event looked like an already
            // synchronized version-1 event and was never queued.
            if ($record->isDirty(array_diff(array_keys($record->getDirty()), ['sync_version']))) {
                $record->sync_version = (int) ($record->getRawOriginal('sync_version') ?: 1) + 1;
            }
        });
    }
}
