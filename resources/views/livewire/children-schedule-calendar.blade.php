@php
    $calendarStart = $scheduleMonth->copy()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
    $calendarItemsByDate = collect($scheduleItems)->groupBy(fn (array $item): string => $item['date']->toDateString());
@endphp

<div class="app-page">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Family schedule</p>
            <h1 class="page-title">Children's immunization calendar</h1>
            <p class="page-subtitle">View vaccination schedules for all children linked to your account.</p>
        </div>
    </div>

<section class="app-card min-w-0 overflow-hidden">
    <div class="app-card-header flex min-w-0 flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow">Family schedule</p>
            <h2 class="app-card-title mt-1">Children’s immunization calendar</h2>
        </div>
        <label class="relative">
            <span class="sr-only">Select month and year</span>
            <input type="month" wire:model.live="calendarMonth" wire:loading.attr="disabled" wire:target="calendarMonth" class="app-input !w-auto !py-1.5 disabled:cursor-wait disabled:opacity-60">
            <span wire:loading wire:target="calendarMonth" class="pointer-events-none absolute right-2 top-1/2 size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-teal-200 border-t-teal-600"></span>
        </label>
    </div>

    <div class="min-w-0 p-3 sm:p-5">
        <p class="mb-3 text-sm font-semibold text-slate-700 dark:text-zinc-200">Showing schedules for {{ $scheduleMonth->format('F Y') }}</p>
        <div class="grid w-full min-w-0 grid-cols-7 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400 sm:text-xs">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                <div class="min-w-0 py-2">{{ $weekday }}</div>
            @endforeach
        </div>
        <div class="grid w-full min-w-0 grid-cols-7 overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-800">
            @for ($dayIndex = 0; $dayIndex < 42; $dayIndex++)
                @php
                    $calendarDay = $calendarStart->copy()->addDays($dayIndex);
                    $dayItems = $calendarItemsByDate->get($calendarDay->toDateString(), collect());
                    $isCurrentMonth = $calendarDay->month === $scheduleMonth->month;
                    $isToday = $calendarDay->isToday();
                @endphp
                <button type="button" wire:click="selectScheduleDate('{{ $calendarDay->toDateString() }}')" wire:loading.attr="disabled" wire:target="selectScheduleDate" class="min-h-20 min-w-0 overflow-hidden border-b border-r border-slate-100 p-1.5 text-left transition hover:bg-teal-50 disabled:cursor-wait dark:border-zinc-800 dark:hover:bg-zinc-800 sm:min-h-24 sm:p-2 {{ $isCurrentMonth ? 'bg-white dark:bg-zinc-900' : 'bg-slate-50/70 text-slate-300 dark:bg-zinc-950/60 dark:text-zinc-700' }} {{ $selectedScheduleDateValue?->isSameDay($calendarDay) ? 'bg-teal-50 ring-2 ring-inset ring-teal-500 dark:bg-teal-950/40' : '' }}" aria-label="Show doses for {{ $calendarDay->format('F j, Y') }}">
                    <div class="flex justify-end">
                        <span class="{{ $isToday ? 'flex size-6 items-center justify-center rounded-full bg-teal-600 font-bold text-white' : 'text-xs font-medium text-slate-500 dark:text-zinc-400' }}">{{ $calendarDay->day }}</span>
                    </div>
                    <div class="mt-1 space-y-1">
                        @foreach ($dayItems->take(3) as $item)
                            @php
                                $eventClass = match ($item['status']) {
                                    'completed' => 'bg-emerald-100 text-emerald-700',
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'overdue', 'delayed' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-sky-100 text-sky-700',
                                };
                            @endphp
                            <div class="min-w-0 truncate rounded px-1 py-0.5 text-[9px] font-semibold {{ $eventClass }}" title="{{ $item['child'] }} · {{ $item['vaccine'] }} dose {{ $item['dose'] }}">
                                {{ str($item['child'])->before(' ') }}: {{ str($item['vaccine'])->limit(8) }}
                            </div>
                        @endforeach
                        @if ($dayItems->count() > 3)
                            <div class="text-[9px] font-semibold text-slate-400">+{{ $dayItems->count() - 3 }} more</div>
                        @endif
                    </div>
                </button>
            @endfor
        </div>
        <div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-600 dark:text-zinc-300">
            <span><i class="mr-1 inline-block size-2 rounded-full bg-sky-500"></i>Due</span>
            <span><i class="mr-1 inline-block size-2 rounded-full bg-emerald-500"></i>Completed</span>
            <span><i class="mr-1 inline-block size-2 rounded-full bg-amber-500"></i>Pending</span>
            <span><i class="mr-1 inline-block size-2 rounded-full bg-rose-500"></i>Overdue</span>
        </div>
    </div>
</section>

<div class="{{ $selectedScheduleDateValue ? 'grid lg:grid-cols-2' : '' }} gap-5">
    @if ($selectedScheduleDateValue)
        <section class="app-card overflow-hidden">
            <div class="app-card-header flex flex-wrap items-center justify-between gap-2 bg-slate-100 dark:bg-zinc-800">
                <h2 class="app-card-title">Recent doses in this {{ $selectedScheduleDateValue->format('F j, Y') }}</h2>
                <button type="button" wire:click="clearScheduleDate" class="text-sm font-semibold text-slate-600 hover:text-slate-800 dark:text-zinc-300 dark:hover:text-white">Clear date</button>
            </div>
            <div class="divide-y divide-slate-100 px-5 dark:divide-zinc-800">
                @forelse ($selectedScheduleItems as $item)
                    <div class="flex min-w-0 flex-wrap items-center gap-3 py-4">
                        <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 text-sm font-semibold text-teal-700 ring-1 ring-teal-200 dark:bg-teal-950 dark:text-teal-300 dark:ring-teal-800">
                            @if ($item['photo_path'])
                                <img src="{{ route('children.photo', $item['child_id']) }}" alt="Photo of {{ $item['child'] }}" class="size-full object-cover">
                            @else
                                {{ str($item['child'])->substr(0, 1) }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-950 dark:text-white">{{ $item['child'] }}</p>
                            <p class="text-sm text-slate-500">{{ $item['vaccine'] }} · Dose {{ $item['dose'] }}</p>
                        </div>
                        <span class="status-pill shrink-0 text-[10px] {{ $item['status'] === 'completed' ? 'status-verified' : ($item['status'] === 'pending' ? 'status-pending' : ($item['status'] === 'overdue' ? 'status-rejected' : 'bg-sky-100 text-sky-700')) }}">{{ str($item['status'])->replace('_', ' ')->headline() }}</span>
                    </div>
                @empty
                    <p class="py-5 text-sm text-slate-500">No doses are scheduled for this date.</p>
                @endforelse
            </div>
        </section>
    @endif

    <section class="app-card overflow-hidden">
        <div class="app-card-header bg-slate-100 dark:bg-zinc-800">
            <h2 class="app-card-title">Upcoming and recent doses</h2>
        </div>
        <div class="divide-y divide-slate-100 px-5 dark:divide-zinc-800">
            @forelse ($upcomingScheduleItems as $item)
                <div class="flex min-w-0 flex-wrap items-center gap-3 py-4">
                    <div class="w-24 shrink-0 text-center"><p class="text-xs font-semibold text-teal-700">{{ $item['date']->format('M d, Y') }}</p></div>
                    <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 text-sm font-semibold text-teal-700 ring-1 ring-teal-200 dark:bg-teal-950 dark:text-teal-300 dark:ring-teal-800">
                        @if ($item['photo_path'])
                            <img src="{{ route('children.photo', $item['child_id']) }}" alt="Photo of {{ $item['child'] }}" class="size-full object-cover">
                        @else
                            {{ str($item['child'])->substr(0, 1) }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-slate-950 dark:text-white">{{ $item['child'] }}</p>
                        <p class="truncate text-sm text-slate-500">{{ $item['vaccine'] }} · Dose {{ $item['dose'] }}</p>
                    </div>
                    <span class="status-pill w-full justify-center text-[10px] sm:w-auto {{ $item['status'] === 'completed' ? 'status-verified' : ($item['status'] === 'pending' ? 'status-pending' : ($item['status'] === 'overdue' ? 'status-rejected' : 'bg-sky-100 text-sky-700')) }}">{{ str($item['status'])->replace('_', ' ')->headline() }}</span>
                </div>
            @empty
                <p class="py-5 text-sm text-slate-500">No upcoming or recent doses are available.</p>
            @endforelse
        </div>
    </section>
</div>
</div>
