<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\AccountAccessNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'password', 'role', 'roles', 'barangay_id', 'municipality_id', 'is_active', 'invitation_accepted_at', 'archived_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable, UsesUuidPrimaryKey;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'roles' => 'array',
            'is_active' => 'boolean',
            'invitation_accepted_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $phone = preg_replace('/(?!^\+)\D+/', '', trim($phone) ?? '');

        return blank($phone) ? null : $phone;
    }

    public static function findByLogin(string $login): ?self
    {
        $login = trim($login);
        $phone = self::normalizePhone($login);

        return self::query()
            ->notArchived()
            ->where(function ($query) use ($login, $phone): void {
                $query->where('email', $login);

                if (filled($phone)) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->first();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function hasEmailLogin(): bool
    {
        return filled($this->email);
    }

    public function hasSmsLogin(): bool
    {
        return filled($this->phone);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AccountAccessNotification($token));
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    /**
     * @return HasMany<ChildProfile, $this>
     */
    public function childrenCreated(): HasMany
    {
        return $this->hasMany(ChildProfile::class, 'created_by');
    }

    /**
     * @return HasMany<VaccinationRecord, $this>
     */
    public function vaccinationRecords(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class, 'recorded_by');
    }

    /**
     * @return BelongsToMany<ChildProfile, $this>
     */
    public function linkedChildren(): BelongsToMany
    {
        return $this->belongsToMany(ChildProfile::class, 'child_parent')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function isBarangayAdmin(): bool
    {
        return $this->hasRole('barangay_admin');
    }

    public function isMunicipalAdmin(): bool
    {
        return $this->hasRole('municipal_admin');
    }

    public function isNurse(): bool
    {
        return $this->hasRole('nurse');
    }

    public function isParent(): bool
    {
        return $this->hasRole('parent');
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->rolesList(), true);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $roles = array_values(array_unique($roles));

        $this->forceFill([
            'roles' => $roles,
            'role' => $roles[0] ?? $this->role,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function rolesList(): array
    {
        $roles = $this->roles;

        if (is_array($roles) && $roles !== []) {
            return array_values(array_unique(array_map('strval', $roles)));
        }

        return [$this->role === 'admin' ? 'superadmin' : $this->role];
    }

    public function displayRole(): string
    {
        return collect($this->rolesList())
            ->map(fn (string $role) => str($role)->replace('_', ' ')->title())
            ->implode(' + ');
    }

    public function canManagePlatform(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageBarangayStaff(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin();
    }

    public function canManageNurses(): bool
    {
        return $this->isMunicipalAdmin() || $this->isBarangayAdmin();
    }

    public function canManageBarangayAdmins(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageGroups(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageChildren(): bool
    {
        return $this->isNurse();
    }

    public function canViewChildrenRegistry(): bool
    {
        return $this->isMunicipalAdmin() || $this->isBarangayAdmin() || $this->isNurse() || $this->isParent();
    }

    public function canVerifyVaccinations(): bool
    {
        return $this->isNurse();
    }

    public function canSubmitAefiReports(): bool
    {
        return $this->isNurse();
    }

    public function canViewOversight(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin();
    }

    public function canViewVerificationQueue(): bool
    {
        return $this->isMunicipalAdmin() || $this->isBarangayAdmin() || $this->isNurse();
    }

    public function canViewAefiReports(): bool
    {
        return $this->isMunicipalAdmin() || $this->isBarangayAdmin() || $this->isNurse();
    }

    /** @return Collection<int, string> */
    public function accessibleBarangayIds(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Barangay::query()->pluck('id');
        }
        if ($this->isMunicipalAdmin() && $this->municipality_id) {
            return Barangay::where('municipality_id', $this->municipality_id)->pluck('id');
        }

        return $this->barangay_id ? collect([$this->barangay_id]) : collect();
    }

    public function canManageInventory(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin() || $this->isNurse();
    }

    public function canViewDuplicates(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin() || $this->isNurse();
    }

    public function canMergeDuplicates(): bool
    {
        return $this->canViewDuplicates();
    }

    public function canViewDefaulters(): bool
    {
        return $this->isMunicipalAdmin() || $this->isBarangayAdmin() || $this->isNurse();
    }

    public function canManageAnnouncements(): bool
    {
        return $this->canManageBarangayStaff() || $this->isNurse();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
