<span
    {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full shadow-md ring-4 ring-emerald-100/80 dark:ring-emerald-950/60']) }}
    aria-label="{{ config('rhu.name', 'Child Vaccination Management System') }}"
>
    <img
        class="h-full w-full rounded-full object-contain"
        src="{{ asset('images/child_vacc_icon.png') }}"
        alt="{{ config('rhu.name', 'Child Vaccination Management System') }}"
        onerror="this.hidden = true; this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('flex');"
    >
    <span
        class="hidden h-full w-full items-center justify-center rounded-full bg-[linear-gradient(145deg,#047857,#0e7490)] text-[0.65rem] font-bold tracking-[0.08em] text-white"
        aria-hidden="true"
    >RHU</span>
</span>
