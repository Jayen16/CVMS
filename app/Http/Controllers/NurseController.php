<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NurseController extends Controller
{
    public function index(): View
    {
        $this->authorizeAdmin();

        return view('nurses.index', [
            'nurses' => User::query()
                ->where('role', 'nurse')
                ->with('barangay')
                ->latest()
                ->paginate(12),
            'barangays' => Barangay::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'barangay_id' => ['nullable', 'exists:barangays,id'],
            'barangay_name' => ['nullable', 'string', 'max:255'],
        ]);

        $barangayId = $validated['barangay_id'] ?? null;

        if (! $barangayId && filled($validated['barangay_name'] ?? null)) {
            $barangayId = Barangay::firstOrCreate(['name' => $validated['barangay_name']])->id;
        }

        $nurse = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::password(32),
            'role' => 'nurse',
            'barangay_id' => $barangayId,
            'is_active' => false,
            'invitation_accepted_at' => null,
        ]);

        Password::sendResetLink(['email' => $nurse->email]);

        return to_route('nurses.index')->with('status', 'Nurse account created. A password setup link was sent by email.');
    }

    public function resendSetupLink(User $nurse): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->authorizeNurse($nurse);

        Password::sendResetLink(['email' => $nurse->email]);

        return to_route('nurses.index')->with('status', 'Password setup link sent again.');
    }

    public function toggle(User $nurse): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->authorizeNurse($nurse);

        abort_if($nurse->invitation_accepted_at === null, 422, 'Nurse must configure the account before status can be changed.');

        $nurse->update(['is_active' => ! $nurse->is_active]);

        return to_route('nurses.index')->with('status', 'Nurse status updated.');
    }

    public function destroy(User $nurse): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->authorizeNurse($nurse);

        $nurse->delete();

        return to_route('nurses.index')->with('status', 'Nurse account removed.');
    }

    private function authorizeNurse(User $user): void
    {
        abort_unless($user->isNurse(), 404);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }
}
