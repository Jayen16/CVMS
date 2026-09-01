<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClinicAnnouncement extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'region_id',
        'province_id',
        'municipality_id',
        'barangay_id',
        'created_by',
        'title',
        'category',
        'audience',
        'starts_on',
        'ends_on',
        'location',
        'message',
        'active',
        'sync_uuid',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isNurse() && ! $user->isBarangayAdmin()) {
            return $query;
        }

        $regionId = $user->barangay?->municipalityRelation?->province?->region_id;
        $provinceId = $user->barangay?->municipalityRelation?->province_id;
        $municipalityId = $user->barangay?->municipality_id ?? $user->municipality_id;

        return $query->where(function (Builder $scope) use ($user, $regionId, $provinceId, $municipalityId): void {
            $scope->where(function (Builder $global): void {
                $global->whereNull('region_id')->whereNull('province_id')->whereNull('municipality_id')->whereNull('barangay_id');
            });

            if ($user->barangay_id) {
                $scope->orWhere('barangay_id', $user->barangay_id);
            }

            if ($regionId || $provinceId || $municipalityId) {
                $scope->orWhere(function (Builder $target) use ($regionId, $provinceId, $municipalityId): void {
                    $target->whereNull('barangay_id')->where(function (Builder $location) use ($regionId, $provinceId, $municipalityId): void {
                        $location->where('region_id', $regionId);
                        if ($provinceId) {
                            $location->orWhere('province_id', $provinceId);
                        }
                        if ($municipalityId) {
                            $location->orWhere('municipality_id', $municipalityId);
                        }
                    });
                });
            }
        });
    }

    public function scopeInLocation(Builder $query, ?string $regionId, ?string $provinceId, ?string $municipalityId, ?string $barangayId): Builder
    {
        if (! $regionId) {
            return $query;
        }

        $barangayIds = Barangay::query()
            ->when($barangayId, fn (Builder $builder) => $builder->whereKey($barangayId))
            ->when(! $barangayId && $municipalityId, fn (Builder $builder) => $builder->where('municipality_id', $municipalityId))
            ->when(! $barangayId && ! $municipalityId && $provinceId, fn (Builder $builder) => $builder->whereHas('municipalityRelation', fn (Builder $location) => $location->where('province_id', $provinceId)))
            ->when(! $barangayId && ! $municipalityId && ! $provinceId, fn (Builder $builder) => $builder->whereHas('municipalityRelation.province', fn (Builder $location) => $location->where('region_id', $regionId)))
            ->pluck('id');

        return $query->where(function (Builder $scope) use ($regionId, $provinceId, $municipalityId, $barangayIds): void {
            $scope->whereNull('region_id')->whereNull('province_id')->whereNull('municipality_id')->whereNull('barangay_id')
                ->orWhere('region_id', $regionId)
                ->when($provinceId, fn (Builder $target) => $target->orWhere('province_id', $provinceId))
                ->when($municipalityId, fn (Builder $target) => $target->orWhere('municipality_id', $municipalityId))
                ->when($barangayIds->isNotEmpty(), fn (Builder $target) => $target->orWhereIn('barangay_id', $barangayIds));
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::creating(function (ClinicAnnouncement $announcement): void {
            if (blank($announcement->sync_uuid)) {
                $announcement->sync_uuid = (string) Str::uuid();
            }
        });
    }
}
