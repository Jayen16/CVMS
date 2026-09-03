<x-layouts::auth :title="__('Activate facility')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Connect this facility')" :description="__('Enter the central system URL and one-time activation code.')" />
        <form method="POST" action="{{ route('facility.activate.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input name="central_url" label="Central system URL" type="url" required placeholder="https://central.example.com" />
            <flux:input name="activation_code" label="Activation code" required maxlength="32" />
            @error('central_url') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('activation_code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <flux:button variant="primary" type="submit">Connect to central</flux:button>
        </form>
    </div>
</x-layouts::auth>
