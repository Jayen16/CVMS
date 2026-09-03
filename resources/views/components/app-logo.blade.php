@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="{{ config('rhu.short_name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-10 items-center justify-center">
            <span class="rhu-monogram size-10 text-[0.6rem]" aria-hidden="true">RHU</span>
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ config('rhu.short_name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-10 items-center justify-center">
            <span class="rhu-monogram size-10 text-[0.6rem]" aria-hidden="true">RHU</span>
        </x-slot>
    </flux:brand>
@endif
