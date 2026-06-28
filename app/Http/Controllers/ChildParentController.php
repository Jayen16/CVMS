<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChildParentController extends Controller
{
    public function store(Request $request, ChildProfile $child): RedirectResponse
    {
        $this->authorizeChildAccess($child);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'relationship' => ['required', 'string', 'max:255'],
        ]);

        $parent = User::where('email', $validated['email'])->first();
        $shouldSendSetupLink = false;
        $status = 'Parent account linked to child profile.';

        if ($parent === null) {
            $request->validate([
                'email' => [Rule::unique('users', 'email')],
            ]);

            $parent = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Str::password(32),
                'role' => 'parent',
                'invitation_accepted_at' => null,
            ]);

            $shouldSendSetupLink = true;
            $status = 'Parent account linked to child profile. A password setup link was sent by email.';
        } else {
            abort_unless($parent->isParent(), 422, 'This email already belongs to a non-parent account.');

            if (filled($validated['phone'] ?? null) && blank($parent->phone)) {
                $parent->update(['phone' => $validated['phone']]);
            }

            if ($parent->invitation_accepted_at === null) {
                $shouldSendSetupLink = true;
                $status = 'Parent account linked to child profile. A password setup link was sent again.';
            }
        }

        $child->parents()->syncWithoutDetaching([
            $parent->id => ['relationship' => $validated['relationship']],
        ]);

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

        Password::sendResetLink(['email' => $parent->email]);

        return to_route('children.show', $child)->with('status', 'Password setup link sent again.');
    }

    public function destroy(ChildProfile $child, User $parent): RedirectResponse
    {
        $this->authorizeUnlink($child, $parent);

        $child->parents()->detach($parent->id);

        if (auth()->user()->isParent()) {
            return to_route('children.index')->with('status', 'Child profile unlinked from your parent account.');
        }

        return to_route('children.show', $child)->with('status', 'Parent account unlinked from child profile.');
    }

    private function authorizeChildAccess(ChildProfile $child): void
    {
        abort_if(
            auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id,
            403
        );

        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);
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
