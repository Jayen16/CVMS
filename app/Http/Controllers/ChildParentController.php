<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\User;
use App\Services\AccountRecoveryService;
use App\Services\OfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ChildParentController extends Controller
{
    public function store(Request $request, ChildProfile $child, AccountRecoveryService $recovery): RedirectResponse
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
        $setupChannel = null;
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
                $setupChannel = 'email';
            } else {
                $setupChannel = 'sms';
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
                $setupChannel = 'email';
            } elseif ($parent->invitation_accepted_at === null && filled($parent->phone)) {
                $setupChannel = 'sms';
            }
        }

        // Keep every existing parent link. `sync()` replaces the current set of
        // pivot rows, so only touch this parent's row when linking an account.
        if ($child->parents()->whereKey($parent->id)->exists()) {
            $child->parents()->updateExistingPivot($parent->id, [
                'relationship' => $validated['relationship'],
            ]);
        } else {
            $child->parents()->attach($parent->id, [
                'relationship' => $validated['relationship'],
            ]);
        }
        app(OfflineSyncService::class)->queueGuardian($parent);
        app(OfflineSyncService::class)->queueRelationship($child, $parent, $validated['relationship']);

        if ($setupChannel !== null) {
            try {
                $recovery->send($parent, $setupChannel);
            } catch (Throwable $exception) {
                report($exception);

                return to_route('children.show', $child)
                    ->with('toast_error', 'Parent account linked, but the password setup link could not be sent by '.strtoupper($setupChannel).'. '.$exception->getMessage());
            }

            $status = $setupChannel === 'email'
                ? 'Parent account linked to child profile. A password setup link was sent by email.'
                : 'Parent account linked to child profile. A password setup link was sent successfully by SMS.';
        }

        return to_route('children.show', $child)->with('status', $status);
    }

    public function resendSetupLink(Request $request, ChildProfile $child, User $parent): RedirectResponse|JsonResponse
    {
        $this->authorizeChildAccess($child);

        abort_unless($parent->isParent(), 404);
        abort_unless($child->parents()->whereKey($parent->id)->exists(), 404);
        abort_if($parent->invitation_accepted_at !== null, 422, 'Parent has already configured the account.');
        abort_if(blank($parent->email), 422, 'This parent does not have an email address for password setup links.');

        Password::sendResetLink(['email' => $parent->email]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Password setup link sent again.']);
        }

        return to_route('children.show', $child)->with('status', 'Password setup link sent again.');
    }

    public function update(Request $request, ChildProfile $child, User $parent): RedirectResponse
    {
        $this->authorizeChildAccess($child);
        abort_unless($parent->isParent() && $child->parents()->whereKey($parent->id)->exists(), 404);

        $validator = Validator::make($request->all(), [
            'edit_name' => ['required', 'string', 'max:255'],
            'edit_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($parent->id)],
            'edit_phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($parent->id)],
            'edit_relationship' => ['required', 'string', 'max:255', Rule::in([
                'mother', 'father', 'guardian', 'aunt', 'uncle', 'grandmother', 'grandfather', 'other',
            ])],
        ]);

        if ($validator->fails()) {
            return redirect()->to(route('children.show', ['child' => $child, 'tab' => 'parents']))
                ->withErrors($validator, 'editParent')
                ->withInput()
                ->with('edit_parent_id', $parent->id);
        }

        $validated = $validator->validated();

        $validated['email'] = filled($validated['edit_email'] ?? null) ? strtolower(trim($validated['edit_email'])) : null;
        $validated['phone'] = User::normalizePhone($validated['edit_phone'] ?? null);

        if (blank($validated['email']) && blank($validated['phone'])) {
            return redirect()->to(route('children.show', ['child' => $child, 'tab' => 'parents']))
                ->withErrors(['edit_phone' => 'Add an email address or phone number for this parent.'], 'editParent')
                ->withInput()
                ->with('edit_parent_id', $parent->id);
        }

        $parent->update([
            'name' => $validated['edit_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);
        $child->parents()->updateExistingPivot($parent->id, ['relationship' => $validated['edit_relationship']]);

        app(OfflineSyncService::class)->queueGuardian($parent->fresh());
        app(OfflineSyncService::class)->queueRelationship($child, $parent, $validated['edit_relationship']);

        return to_route('children.show', [$child, 'tab' => 'parents'])->with('status', 'Parent information updated.');
    }

    public function sendPasswordLink(Request $request, ChildProfile $child, User $parent, AccountRecoveryService $recovery): RedirectResponse|JsonResponse
    {
        $this->authorizeChildAccess($child);
        abort_unless($parent->isParent() && $child->parents()->whereKey($parent->id)->exists(), 404);
        $channel = $request->validate(['channel' => ['required', 'in:email,sms']])['channel'];
        try {
            $recovery->send($parent, $channel);
        } catch (Throwable $exception) {
            report($exception);
            $message = 'The password link could not be sent by '.strtoupper($channel).'. '.$exception->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return to_route('children.show', $child)->with('toast_error', $message);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Parent password reset link sent by '.strtoupper($channel).'.']);
        }

        return to_route('children.show', $child)->with('status', 'Parent password reset link sent by '.strtoupper($channel).'.');
    }

    public function destroy(ChildProfile $child, User $parent): RedirectResponse
    {
        $this->authorizeUnlink($child, $parent);

        $linkedParent = $child->parents()->whereKey($parent->id)->first();
        $relationship = (string) ($linkedParent?->pivot?->getAttribute('relationship') ?? 'guardian');
        $child->parents()->detach($parent->id);
        app(OfflineSyncService::class)->queueRelationship($child, $parent, $relationship, 'deleted');

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
