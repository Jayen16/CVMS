<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\AccountRecoveryService;

class ChildParentController extends Controller
{
    public function store(Request $request, ChildProfile $child): RedirectResponse
    {
        $this->authorizeChildAccess($child);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:32', 'required_without:email'],
            'relationship' => ['required', 'string', 'max:255', Rule::in([
                'mother',
                'father',
                'guardian',
                'aunt',
                'uncle',
                'grandmother',
                'grandfather',
                'other',
            ])],
        ]);

        $validated['email'] = $validated['email'] ?? null;
        $validated['phone'] = User::normalizePhone($validated['phone'] ?? null);

        $parent = User::query()
            ->when(filled($validated['email']), fn ($query) => $query->where('email', $validated['email']))
            ->when(
                filled($validated['phone']),
                fn ($query) => $query->orWhere('phone', $validated['phone'])
            )
            ->first();
        $shouldSendSetupLink = false;
        $status = 'Parent account linked to child profile.';

        if ($parent === null) {
            $uniqueRules = [];

            if (filled($validated['email'])) {
                $uniqueRules['email'] = [Rule::unique('users', 'email')];
            }

            if (filled($validated['phone'])) {
                $uniqueRules['phone'] = [Rule::unique('users', 'phone')];
            }

            if ($uniqueRules !== []) {
                $request->validate($uniqueRules);
            }

            $parent = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Str::password(32),
                'role' => 'parent',
                'roles' => ['parent'],
                'invitation_accepted_at' => null,
            ]);

            if (filled($parent->email)) {
                $shouldSendSetupLink = true;
                $status = 'Parent account linked to child profile. A password setup link was sent by email.';
            } else {
                $status = 'Parent account linked to child profile. The parent can finish sign up using this phone number and a password.';
            }
        } else {
            abort_unless($parent->isParent(), 422, 'This contact already belongs to a non-parent account.');

            $parent->fill([
                'name' => $parent->name ?: $validated['name'],
                'email' => $parent->email ?: $validated['email'],
                'phone' => $parent->phone ?: $validated['phone'],
            ]);

            if ($parent->isDirty(['name', 'email', 'phone'])) {
                $parent->save();
            }

            if ($parent->invitation_accepted_at === null && filled($parent->email)) {
                $shouldSendSetupLink = true;
                $status = 'Parent account linked to child profile. A password setup link was sent again.';
            } elseif ($parent->invitation_accepted_at === null && filled($parent->phone)) {
                $status = 'Parent account linked to child profile. The parent can finish sign up using this phone number and a password.';
            }
        }

        $child->parents()->syncWithoutDetaching([
            $parent->id => ['relationship' => $validated['relationship']],
        ]);
        app(\App\Services\OfflineSyncService::class)->queueGuardian($parent);
        app(\App\Services\OfflineSyncService::class)->queueRelationship($child, $parent, $validated['relationship']);

        if ($shouldSendSetupLink) {
            Password::sendResetLink(['email' => $parent->email]);
        }

        return to_route('children.show', $child)->with('status', $status);
    }

    public function resendSetupLink(ChildProfile $child, User $parent): RedirectResponse
    {
        $this->authorizeChildAccess($child);

        abort_unless($parent->isParent(), 404);
        abort_unless($child->parents()->whereKey($parent->id)->exists(), 404);
        abort_if($parent->invitation_accepted_at !== null, 422, 'Parent has already configured the account.');
        abort_if(blank($parent->email), 422, 'This parent does not have an email address for password setup links.');

        Password::sendResetLink(['email' => $parent->email]);

        return to_route('children.show', $child)->with('status', 'Password setup link sent again.');
    }

    public function sendPasswordLink(Request $request, ChildProfile $child, User $parent, AccountRecoveryService $recovery): RedirectResponse
    {
        $this->authorizeChildAccess($child);
        abort_unless($parent->isParent() && $child->parents()->whereKey($parent->id)->exists(), 404);
        $channel = $request->validate(['channel' => ['required', 'in:email,sms']])['channel'];
        $recovery->send($parent, $channel);
        return to_route('children.show', $child)->with('status', 'Parent password reset link sent by '.strtoupper($channel).'.');
    }

    public function destroy(ChildProfile $child, User $parent): RedirectResponse
    {
        $this->authorizeUnlink($child, $parent);

        $relationship = (string) ($child->parents()->whereKey($parent->id)->first()?->pivot?->relationship ?? 'guardian');
        $child->parents()->detach($parent->id);
        app(\App\Services\OfflineSyncService::class)->queueRelationship($child, $parent, $relationship, 'deleted');

        if (auth()->user()->isParent()) {
            return to_route('children.index')->with('status', 'Child profile unlinked from your parent account.');
        }

        return to_route('children.show', $child)->with('status', 'Parent account unlinked from child profile.');
    }

    private function authorizeChildAccess(ChildProfile $child): void
    {
        abort_unless(auth()->user()->canManageChildren(), 403);
        abort_if($child->barangay_id !== auth()->user()->barangay_id, 403);
    }

    private function authorizeUnlink(ChildProfile $child, User $parent): void
    {
        abort_unless($parent->isParent(), 404);
        abort_unless($child->parents()->whereKey($parent->id)->exists(), 404);

        if (auth()->user()->isParent()) {
            abort_unless(auth()->id() === $parent->id, 403);

            return;
        }

        $this->authorizeChildAccess($child);
    }
}
