<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $pendingParent = $this->pendingParent($input);

        Validator::make($input, [
            ...$this->profileRules($pendingParent?->id),
            'password' => $this->passwordRules(),
        ])->validate();

        $attributes = [
            'name' => $input['name'],
            'email' => ($input['email'] ?? null) ?: null,
            'phone' => User::normalizePhone($input['phone'] ?? null),
            'password' => $input['password'],
            'role' => User::query()->exists() ? 'parent' : 'superadmin',
            'roles' => User::query()->exists() ? ['parent'] : ['superadmin'],
        ];

        if ($pendingParent !== null) {
            $pendingParent->forceFill([
                ...$attributes,
                'invitation_accepted_at' => $pendingParent->invitation_accepted_at ?? Carbon::now(),
                'is_active' => true,
            ])->save();

            return $pendingParent;
        }

        return User::create($attributes);
    }

    /**
     * @param  array<string, string>  $input
     */
    private function pendingParent(array $input): ?User
    {
        $email = ($input['email'] ?? null) ?: null;
        $phone = User::normalizePhone($input['phone'] ?? null);

        if (blank($email) && blank($phone)) {
            return null;
        }

        return User::query()
            ->where(function ($query): void {
                $query->where('role', 'parent')
                    ->orWhereJsonContains('roles', 'parent');
            })
            ->whereNull('invitation_accepted_at')
            ->where(function ($query) use ($email, $phone): void {
                if (filled($email)) {
                    $query->orWhere('email', $email);
                }

                if (filled($phone)) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->first();
    }
}
