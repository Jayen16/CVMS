<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'password' => ['required', 'string', 'min:8'],
            'barangay_id' => ['nullable', 'exists:barangays,id'],
            'barangay_name' => ['nullable', 'string', 'max:255'],
        ]);

        $barangayId = $validated['barangay_id'] ?? null;

        if (! $barangayId && filled($validated['barangay_name'] ?? null)) {
            $barangayId = Barangay::firstOrCreate(['name' => $validated['barangay_name']])->id;
        }

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'nurse',
            'barangay_id' => $barangayId,
        ]);

        return to_route('nurses.index')->with('status', 'Nurse account created.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }
}
