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
#[Fillable(['name', 'email', 'phone', 'password', 'role', 'roles', 'permissions', 'barangay_id', 'municipality_id', 'is_active', 'invitation_accepted_at', 'archived_at', 'archived_by', 'archive_reason'])]
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
            'permissions' => 'array',
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

    public static function smsRecipient(?string $phone): ?string
    {
        $phone = self::normalizePhone($phone);

        if (blank($phone)) {
            return null;
        }
        if (str_starts_with($phone, '09')) {
            return '+63'.substr($phone, 1);
        }
        if (str_starts_with($phone, '639')) {
            return '+'.$phone;
        }

        return $phone;
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
        return $this->isBarangayAdmin();
    }

    public function canManageBarangayAdmins(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin();
    }

    public function canManageGroups(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManagePopulationBackground(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin();
    }

    public function canManageChildren(): bool
    {
        return $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('manage_children'));
    }

    public function canArchiveChildren(): bool
    {
        return $this->isSuperAdmin()
            || $this->isMunicipalAdmin()
            || $this->isBarangayAdmin()
            || ($this->isNurse() && $this->hasNursePermission('archive_children'));
    }

    public function canArchiveReports(): bool
    {
        return $this->isSuperAdmin()
            || $this->isMunicipalAdmin()
            || $this->isBarangayAdmin();
    }

    public function canArchiveAuditLogs(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin();
    }

    public function canViewChildrenRegistry(): bool
    {
        return $this->isMunicipalAdmin() || $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('view_children')) || $this->isParent();
    }

    public function canVerifyVaccinations(): bool
    {
        return $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('verify_vaccinations'));
    }

    public function canSubmitAefiReports(): bool
    {
        return $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('submit_aefi_reports'));
    }

    public function canViewOversight(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin();
    }

    public function canViewVerificationQueue(): bool
    {
        return $this->isMunicipalAdmin() || $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('view_verification_queue'));
    }

    public function canViewAefiReports(): bool
    {
        return $this->isMunicipalAdmin() || $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('view_aefi_reports'));
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

    public function canAccessBarangay(?string $barangayId): bool
    {
        return filled($barangayId) && $this->accessibleBarangayIds()->contains($barangayId);
    }

    public function canManageInventory(): bool
    {
        return $this->isSuperAdmin() || $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('manage_inventory'));
    }

    public function canViewInventory(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('view_inventory'));
    }

    public function canViewDuplicates(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('view_duplicates'));
    }

    public function canMergeDuplicates(): bool
    {
        return $this->isSuperAdmin() || $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('merge_duplicates'));
    }

    public function canViewDefaulters(): bool
    {
        return $this->isSuperAdmin() || $this->isMunicipalAdmin() || $this->isBarangayAdmin() || ($this->isNurse() && $this->hasNursePermission('view_defaulters'));
    }

    public function canManageAnnouncements(): bool
    {
        return $this->canManageBarangayStaff() || ($this->isNurse() && $this->hasNursePermission('manage_announcements'));
    }

    /** @return array<string, string> */
    public static function nursePermissionDefinitions(): array
    {
        return [
            'view_children' => 'View child registry',
            'manage_children' => 'Create and update child records',
            'archive_children' => 'Archive and restore child records',
            'verify_vaccinations' => 'Verify or reject vaccinations',
            'view_verification_queue' => 'View verification queue',
            'submit_aefi_reports' => 'Submit AEFI reports',
            'view_aefi_reports' => 'View AEFI reports',
            'manage_inventory' => 'Manage vaccine inventory',
            'view_inventory' => 'View vaccine inventory',
            'view_duplicates' => 'View possible duplicates',
            'merge_duplicates' => 'Merge duplicate child records',
            'view_defaulters' => 'View defaulters',
            'manage_announcements' => 'Manage clinic announcements',
        ];
    }

    /** @return array<int, string> */
    public static function hiddenNursePermissionKeys(): array
    {
        return [
            'submit_aefi_reports',
            'view_aefi_reports',
            'view_duplicates',
            'merge_duplicates',
            'view_defaulters',
            'manage_announcements',
        ];
    }

    /** @return array<int, string> */
    public static function defaultNursePermissions(): array
    {
        return array_keys(self::nursePermissionDefinitions());
    }

    /** @return array<string, array<string, string>> */
    public static function nursePermissionGroups(): array
    {
        $permissions = self::nursePermissionDefinitions();

        return [
            'Child Records' => array_intersect_key($permissions, array_flip([
                'view_children',
                'manage_children',
                'archive_children',
                'view_defaulters',
                'view_duplicates',
                'merge_duplicates',
            ])),
            'Vaccination' => array_intersect_key($permissions, array_flip([
                'verify_vaccinations',
                'view_verification_queue',
                'submit_aefi_reports',
                'view_aefi_reports',
            ])),
            'Inventory' => array_intersect_key($permissions, array_flip([
                'view_inventory',
                'manage_inventory',
            ])),
            'Communications' => array_intersect_key($permissions, array_flip([
                'manage_announcements',
            ])),
        ];
    }

    /** @return array<int, string> */
    public function nursePermissions(): array
    {
        $permissions = $this->permissions;

        return is_array($permissions)
            ? array_values(array_intersect(array_keys(self::nursePermissionDefinitions()), $permissions))
            : self::defaultNursePermissions();
    }

    public function hasNursePermission(string $permission): bool
    {
        return in_array($permission, $this->nursePermissions(), true);
    }

    /** @param array<int, string> $permissions */
    public function syncNursePermissions(array $permissions): void
    {
        $this->permissions = array_values(array_intersect(
            array_keys(self::nursePermissionDefinitions()),
            array_unique($permissions),
        ));
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
