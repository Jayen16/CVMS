<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ChildProfile extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'barangay_id',
        'created_by',
        'first_name',
        'middle_name',
        'last_name',
        'birthdate',
        'sex',
        'guardian_name',
        'guardian_contact',
        'address',
        'vaccine_card_token',
        'sync_uuid',
        'facility_uuid',
        'registered_by_uuid',
        'registered_by_name',
        'registered_by_role',
        'sync_version',
        'archived_at',
        'archived_by',
        'archive_reason',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'archived_at' => 'datetime',
            'sync_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /** @param Builder<self> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }
        if ($user->isParent()) {
            return $query->whereHas('parents', fn (Builder $parents) => $parents->whereKey($user->id));
        }

        return $query->whereIn('barangay_id', $user->accessibleBarangayIds());
    }

    /** @param Builder<self> $query */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull($query->getModel()->qualifyColumn('archived_at'));
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * @return HasMany<VaccinationRecord, $this>
     */
    public function vaccinations(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class);
    }

    /**
     * @return HasMany<AdverseEventReport, $this>
     */
    public function adverseEventReports(): HasMany
    {
        return $this->hasMany(AdverseEventReport::class);
    }

    /**
     * @return HasMany<ChildVaccineSeriesVersion, $this>
     */
    public function seriesVersions(): HasMany
    {
        return $this->hasMany(ChildVaccineSeriesVersion::class, 'child_profile_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'child_parent')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }

    public function ageLabel(): string
    {
        $birthdate = Carbon::parse($this->birthdate);

        if ($birthdate->diffInMonths() < 24) {
            return (int) $birthdate->diffInMonths().' months';
        }

        return (int) $birthdate->diffInYears().' years';
    }

    public function ensureVaccineCardToken(): string
    {
        if (filled($this->vaccine_card_token)) {
            return $this->vaccine_card_token;
        }

        $this->forceFill([
            'vaccine_card_token' => (string) Str::uuid(),
        ])->save();

        return $this->vaccine_card_token;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('not_archived', fn (Builder $query) => $query->whereNull($query->getModel()->qualifyColumn('archived_at')));

        static::creating(function (ChildProfile $child): void {
            if (blank($child->vaccine_card_token)) {
                $child->vaccine_card_token = (string) Str::uuid();
            }

            if (blank($child->sync_uuid)) {
                $child->sync_uuid = (string) Str::uuid();
            }
        });

        static::updating(function (ChildProfile $child): void {
            if ($child->isDirty(array_diff($child->getDirty(), ['sync_version']))) {
                $child->sync_version = (int) ($child->getRawOriginal('sync_version') ?: 1) + 1;
            }
        });
    }
}
