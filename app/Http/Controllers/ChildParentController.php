<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'password' => ['nullable', 'string', 'min:8'],
            'relationship' => ['required', 'string', 'max:255'],
        ]);

        $parent = User::where('email', $validated['email'])->first();

        if ($parent === null) {
            $request->validate([
                'email' => [Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8'],
            ]);

            $parent = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => 'parent',
            ]);
        } else {
            abort_unless($parent->isParent(), 422, 'This email already belongs to a non-parent account.');

            if (filled($validated['phone'] ?? null) && blank($parent->phone)) {
                $parent->update(['phone' => $validated['phone']]);
            }
        }

        $child->parents()->syncWithoutDetaching([
            $parent->id => ['relationship' => $validated['relationship']],
        ]);

        return to_route('children.show', $child)->with('status', 'Parent account linked to child profile.');
    }

    private function authorizeChildAccess(ChildProfile $child): void
    {
        abort_if(
            auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id,
            403
        );

        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);
    }
}
