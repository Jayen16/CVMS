<x-layouts::auth :title="__('Set up barangay account')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Create the first barangay account')"
            :description="__('Set up the administrator account for :barangay. This account will manage local staff and records.', ['barangay' => $barangay->name])"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('facility.setup.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input name="name" label="Full name" value="{{ $installation->setup_user_name }}" readonly />
            <flux:input name="email" label="Registered email address" value="{{ $installation->setup_user_email }}" type="email" readonly />
            <flux:input name="password" label="Password" type="password" required autocomplete="new-password" viewable />
            <flux:input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" viewable />

            @if ($errors->any())
                <div class="text-sm text-red-600">{{ $errors->first() }}</div>
            @endif

            <flux:button variant="primary" type="submit">Create barangay administrator account</flux:button>
        </form>
    </div>
</x-layouts::auth>
