<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ChildProfile extends Model
{
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
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<VaccinationRecord, $this>
     */
    public function vaccinations(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class);
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
}
