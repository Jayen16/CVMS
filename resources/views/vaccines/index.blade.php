<x-layouts::app.sidebar :title="__('Vaccine information')">
<flux:main>
<div class="app-page">
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ auth()->user()->isParent() ? 'Parent resources' : 'Clinical reference' }}</p>
            <h1 class="page-title">Vaccine information</h1>
            <p class="page-subtitle">{{ auth()->user()->isParent() ? 'Browse the vaccines in your child’s immunization schedule and read parent-friendly information about each one.' : 'Browse vaccine information, dose timing, safety guidance, and trusted references for clinic conversations.' }}</p>
        </div>
        @if (auth()->user()->isParent())
            <a href="{{ route('family-schedule.index') }}" class="app-button-secondary" wire:navigate>View family schedule</a>
        @endif
    </div>

    <div class="vaccine-library-grid">
        @forelse ($vaccines as $vaccine)
            <a href="{{ route('vaccine-information.show', $vaccine) }}" class="vaccine-library-card" wire:navigate>
                <img src="{{ $vaccine->parent_information_image }}" alt="{{ $vaccine->name }} illustration" class="vaccine-library-image">
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">{{ $vaccine->code }}</p>
                            <h2 class="mt-1 text-base font-bold text-slate-950 dark:text-white">{{ $vaccine->name }}</h2>
                        </div>
                        <flux:icon.chevron-right class="size-4 shrink-0 text-slate-400" />
                    </div>
                    <p class="mt-2 line-clamp-3 text-sm leading-5 text-slate-600 dark:text-zinc-300">{{ data_get($vaccine->parent_information, 'summary', 'Parent-friendly information is available for this vaccine.') }}</p>
                    <p class="mt-3 text-xs font-semibold text-slate-500 dark:text-zinc-400">{{ $vaccine->schedules->count() }} scheduled dose{{ $vaccine->schedules->count() === 1 ? '' : 's' }} · Read details →</p>
                </div>
            </a>
        @empty
            <div class="app-card p-6 text-sm text-slate-500">No active vaccine information is available yet.</div>
        @endforelse
    </div>
</div>
</flux:main>
</x-layouts::app.sidebar>
