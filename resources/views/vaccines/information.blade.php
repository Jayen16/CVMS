<x-layouts::app.sidebar :title="$vaccine->name.' information'">
<flux:main>
<div class="app-page">
    <div class="flex flex-wrap gap-4">
        <a href="{{ route('vaccine-information.index') }}" class="text-sm font-semibold text-teal-700 hover:underline dark:text-teal-300" wire:navigate>← All vaccine information</a>
        <a href="{{ route('children.index') }}" class="text-sm font-semibold text-slate-500 hover:text-teal-700 hover:underline dark:text-zinc-400 dark:hover:text-teal-300" wire:navigate>Back to my children</a>
    </div>

    <section class="vaccine-info-hero">
        <div class="flex items-start gap-4">
            <div class="vaccine-info-icon">
                <img src="{{ $deliveryImage }}" alt="Vaccine delivery illustration" class="size-14 rounded-2xl object-cover">
            </div>
            <div>
                <p class="eyebrow">Parent-friendly information</p>
                <h1 class="page-title mt-1">{{ $vaccine->name }}</h1>
                <p class="page-subtitle">{{ $information['summary'] ?? 'Information about this vaccine and its place in the recommended schedule.' }}</p>
            </div>
        </div>
    </section>

    <section x-data="{ tab: 'about' }" class="app-card overflow-hidden">
        <nav class="flex gap-1 overflow-x-auto border-b border-slate-100 p-2 dark:border-zinc-800" aria-label="Vaccine information sections">
            <button type="button" class="vaccine-info-tab" :class="tab === 'about' ? 'is-active' : ''" @click="tab = 'about'">About this vaccine</button>
            <button type="button" class="vaccine-info-tab" :class="tab === 'effects' ? 'is-active' : ''" @click="tab = 'effects'">Side effects</button>
            <button type="button" class="vaccine-info-tab" :class="tab === 'visit' ? 'is-active' : ''" @click="tab = 'visit'">When to visit</button>
            <button type="button" class="vaccine-info-tab" :class="tab === 'references' ? 'is-active' : ''" @click="tab = 'references'">References</button>
        </nav>

        <div x-show="tab === 'about'" class="vaccine-info-content">
            <h2>What is this vaccine?</h2>
            <p>{{ $information['summary'] ?? 'This vaccine is included in the configured childhood immunization schedule.' }}</p>
            <div class="mt-5 rounded-2xl bg-teal-50 p-4 text-sm text-teal-900 dark:bg-teal-950/50 dark:text-teal-100"><strong>Schedule reminder:</strong> The number and timing of doses shown below come from {{ $scheduleSource }}. Your health worker can confirm the appropriate dose for your child.</div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($vaccine->schedules as $schedule)
                    <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dose {{ $schedule->dose_number }}</p><p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $schedule->label }}</p></div>
                @empty
                    <p class="text-sm text-slate-500">No active dose schedule is currently published for this vaccine.</p>
                @endforelse
            </div>
        </div>

        <div x-show="tab === 'effects'" x-cloak class="vaccine-info-content">
            <h2>What side effects may occur?</h2>
            <p>Vaccines can cause side effects, but they are usually mild and short-lived. Follow the advice given by your health worker about what to expect after vaccination.</p>
            <p class="mt-3">If your child has an unexpected or worrying reaction, contact your health worker or health center for advice.</p>
        </div>

        <div x-show="tab === 'visit'" x-cloak class="vaccine-info-content">
            <h2>When should I contact the health center?</h2>
            <p>Contact your health worker if you are concerned about your child after vaccination or if a reaction is unexpected.</p>
            <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100"><strong>Get urgent medical help immediately</strong> for signs of a severe allergic reaction or another serious emergency, such as trouble breathing, swelling of the face or throat, collapse, or a rapidly worsening condition.</div>
            <p class="mt-4 text-sm text-slate-500">This page does not replace an assessment by a qualified health professional.</p>
        </div>

        <div x-show="tab === 'references'" x-cloak class="vaccine-info-content">
            <h2>Trusted references</h2>
            <div class="space-y-3 text-sm"><a href="{{ $safetyUrl }}" target="_blank" rel="noopener" class="block font-semibold text-teal-700 hover:underline dark:text-teal-300">WHO: Vaccines and immunization — vaccine safety ↗</a><a href="{{ $scheduleSourceUrl }}" target="_blank" rel="noopener" class="block font-semibold text-teal-700 hover:underline dark:text-teal-300">{{ $scheduleSource }} ↗</a></div>
        </div>
    </section>
</div>
</flux:main>
</x-layouts::app.sidebar>
