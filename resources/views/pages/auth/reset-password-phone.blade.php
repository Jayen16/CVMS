<x-layouts::auth :title="__('Reset password')">
    <x-auth-header :title="__('Set a new password')" :description="__('Choose a new password for your CVMS account.')" />
    <form method="POST" action="{{ route('password.phone.link.reset', $token) }}" class="flex flex-col gap-6">
        @csrf
        <flux:input name="password" :label="__('New password')" type="password" required autocomplete="new-password" />
        <flux:input name="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" />
        <flux:button variant="primary" type="submit" class="w-full">{{ __('Update password') }}</flux:button>
    </form>
</x-layouts::auth>
