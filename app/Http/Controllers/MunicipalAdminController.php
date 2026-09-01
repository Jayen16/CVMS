<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MunicipalAdminController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'municipality_id' => ['required', 'exists:municipalities,id'],
        ]);
        $admin = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => Str::password(32),
            'role' => 'municipal_admin', 'roles' => ['municipal_admin'],
            'municipality_id' => $data['municipality_id'], 'is_active' => false,
        ]);
        Password::sendResetLink(['email' => $admin->email]);

        return back()->with('status', 'Municipal admin account created and assigned.');
    }

    public function assign(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $data = $request->validate(['municipality_id' => ['required', 'exists:municipalities,id']]);
        $user->update(['municipality_id' => $data['municipality_id']]);

        return back()->with('status', 'User assigned to municipality.');
    }
}
