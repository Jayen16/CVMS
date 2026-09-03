<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopulationBackground extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'municipality_id', 'barangay_id', 'reference_year', 'age_group', 'sex',
        'target_population', 'source', 'created_by', 'updated_by',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param Builder<self> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isMunicipalAdmin()) {
            return $query->where(function (Builder $locations) use ($user): void {
                $locations->where('municipality_id', $user->municipality_id)
                    ->orWhereHas('barangay', fn (Builder $barangay) => $barangay->where('municipality_id', $user->municipality_id));
            });
        }

        $barangayIds = $user->accessibleBarangayIds();

        return $query->where(function (Builder $locations) use ($barangayIds): void {
            $locations->whereIn('barangay_id', $barangayIds)
                ->orWhereHas('barangay', fn (Builder $barangay) => $barangay->whereIn('id', $barangayIds)->whereNotNull('municipality_id'))
                ->orWhereIn('municipality_id', Barangay::whereIn('id', $barangayIds)->pluck('municipality_id'));
        });
    }

    public function locationLabel(): string
    {
        return $this->barangay?->name ?? $this->municipality?->name ?? 'Unassigned';
    }
}
