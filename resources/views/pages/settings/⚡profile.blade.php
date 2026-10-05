<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $current_password = '';
    public $photo = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email ?? '';
        $this->phone = Auth::user()->phone ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $phone = \App\Models\User::normalizePhone($this->phone ?: null);
        $identifierChanged = $this->email !== ($user->email ?? '') || $phone !== ($user->phone ?? null);
        $rules = $this->profileRules($user->id);
        if ($user->isParent() && $identifierChanged) {
            $rules['current_password'] = ['required', 'current_password:web'];
        }
        $validated = $this->validate($rules);
        $validated['phone'] = $phone;
        unset($validated['current_password']);
        $user->fill($validated);

        if ($user->isDirty('email') && filled($validated['email'])) {
            $user->email_verified_at = null;
        } elseif (blank($validated['email'])) {
            $user->email_verified_at = null;
        }

        $user->save();
        if ($user->isParent()) {
            app(\App\Services\OfflineSyncService::class)->queueGuardian($user);
        }
        $this->current_password = '';

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function updateProfilePhoto(): void
    {
        abort_unless(Auth::user()->isParent() || Auth::user()->isNurse(), 403);

        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = Auth::user();
        $oldPhoto = $user->photo_path;
        $user->update(['photo_path' => $this->photo->store('profile-photos', 'local')]);

        if (filled($oldPhoto)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($oldPhoto);
        }

        $this->photo = null;
        Flux::toast(variant: 'success', text: __('Profile photo updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and account contact details')">
        @if (Auth::user()->isParent() || Auth::user()->isNurse())
            <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 text-xl font-bold text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" alt="Profile photo preview" class="size-full object-cover">
                        @elseif (Auth::user()->photo_path)
                            <img src="{{ route('profile.photo') }}" alt="Profile photo of {{ Auth::user()->name }}" class="size-full object-cover">
                        @else
                            {{ str(Auth::user()->name)->substr(0, 1)->upper() }}
                        @endif
                    </div>
                    <div class="min-w-56 flex-1">
                        <flux:heading size="sm">{{ __('Profile photo') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Add a photo so your profile is recognizable on the dashboard.') }}</flux:text>
                        <input wire:model="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-600 file:px-3 file:py-2 file:font-semibold file:text-white hover:file:bg-teal-700 dark:text-zinc-300" />
                        @error('photo') <flux:text class="mt-2 text-sm !text-red-600 dark:!text-red-400">{{ $message }}</flux:text> @enderror
                    </div>
                    <flux:button wire:click="updateProfilePhoto" wire:loading.attr="disabled" wire:target="photo,updateProfilePhoto" variant="primary" type="button">
                        {{ __('Save photo') }}
                    </flux:button>
                </div>
            </div>
        @endif

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" autocomplete="email" />
            </div>

            <div>
                <flux:input wire:model="phone" :label="__('Phone number')" type="text" autocomplete="tel" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            @if (Auth::user()->isParent())
                <flux:input wire:model="current_password" :label="__('Current password (required when changing email or phone)')" type="password" autocomplete="current-password" />
            @endif

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

    </x-pages::settings.layout>
</section>
