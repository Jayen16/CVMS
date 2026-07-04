@php
    $phaseBands = [
        ['label' => 'Infancy', 'start' => 0, 'end' => 12, 'class' => 'bg-lime-100 text-lime-950'],
        ['label' => 'Early Childhood', 'start' => 12, 'end' => 24, 'class' => 'bg-cyan-100 text-cyan-950'],
        ['label' => 'School Age / Adolescence', 'start' => 24, 'end' => 216, 'class' => 'bg-violet-100 text-violet-950'],
    ];
    $scaleTicks = [
        ['label' => 'Birth', 'months' => 0],
        ['label' => '4 wks', 'months' => 1],
        ['label' => '6 wks', 'months' => 1.5],
        ['label' => '8 wks', 'months' => 2],
        ['label' => '10 wks', 'months' => 2.5],
        ['label' => '14 wks', 'months' => 3.5],
        ['label' => '4 mos', 'months' => 4],
        ['label' => '6 mos', 'months' => 6],
        ['label' => '9 mos', 'months' => 9],
        ['label' => '12 mos', 'months' => 12],
        ['label' => '15 mos', 'months' => 15],
        ['label' => '18 mos', 'months' => 18],
        ['label' => '19-24 mos', 'months' => 21.5],
        ['label' => '2-3 yrs', 'months' => 30],
        ['label' => '4-6 yrs', 'months' => 60],
        ['label' => '7-10 yrs', 'months' => 102],
        ['label' => '11-12 yrs', 'months' => 138],
        ['label' => '13-18 yrs', 'months' => 186],
    ];
    $timelineMonths = 216;
@endphp

<div class="app-page">
    <div class="page-heading xl:items-start">
        <div>
            <a href="{{ route('children.show', $child) }}" class="text-sm text-teal-700 hover:underline dark:text-teal-300">Back to profile</a>
            <p class="eyebrow mt-3">Schedule view</p>
            <h1 class="page-title">{{ $child->full_name }} vaccination timeline</h1>
        </div>

        <form method="GET" action="{{ route('children.timeline', $child) }}" class="app-panel flex w-full flex-wrap items-end gap-3 sm:w-auto">
            <label class="grid gap-2 text-sm">
                <span class="font-medium text-slate-800 dark:text-zinc-100">Vaccine</span>
                <select name="vaccine" class="app-input min-w-72">
                    <option value="">All configured vaccines</option>
                    @foreach ($vaccines as $vaccine)
                        <option value="{{ $vaccine->code }}" @selected($selectedVaccine === $vaccine->code)>{{ $vaccine->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="app-button-primary">View</button>
        </form>
    </div>

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="app-panel !p-4">
            <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Vaccination status</h2>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-2">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-zinc-300">
                    <span class="size-3 rounded-sm bg-emerald-500"></span>
                    <span>Given</span>
                </div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-zinc-300">
                    <span class="size-3 rounded-sm bg-amber-400"></span>
                    <span>Pending verification</span>
                </div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-zinc-300">
                    <span class="size-3 rounded-sm bg-sky-400"></span>
                    <span>Upcoming</span>
                </div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-zinc-300">
                    <span class="size-3 rounded-sm bg-red-500"></span>
                    <span>Overdue</span>
                </div>
            </div>
        </div>

        <div class="app-panel !p-4">
            <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Schedule indication</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($indications as $value => $label)
                    <div class="flex min-w-0 items-center gap-2 text-xs font-medium text-slate-600 dark:text-zinc-300">
                        <span class="schedule-indication-{{ str_replace('_', '-', $value) }} h-4 w-8 shrink-0 rounded border border-slate-300"></span>
                        <span class="truncate">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="app-card">
        <div class="app-card-header">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="app-card-title">Routine schedule checklist</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Checklist markers are positioned on the age scale like a Gantt chart.</p>
                </div>
                <div class="text-xs font-medium text-slate-500">Birth to 18 years scale</div>
            </div>
        </div>

        <div @class([
            'overflow-x-auto overflow-y-visible',
            'pb-24' => $selectedVaccine !== '',
        ])>
            <div class="min-w-[1560px] divide-y divide-slate-200 dark:divide-zinc-800">
                <div class="sticky top-0 z-20 grid grid-cols-[260px_1fr] bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 shadow-sm dark:bg-zinc-950 dark:text-zinc-400">
                    <div class="border-r border-slate-200 px-5 py-3 dark:border-zinc-800">Vaccine</div>
                    <div class="relative px-8 py-3">
                        <div class="relative h-16 rounded-xl border border-slate-200 bg-white/80 dark:border-zinc-800 dark:bg-zinc-900/80">
                            @foreach ($phaseBands as $band)
                                <div
                                    class="absolute top-0 flex h-7 items-center justify-center border-b border-slate-200 text-[11px] font-bold uppercase tracking-[0.24em] dark:border-zinc-800 {{ $band['class'] }}"
                                    style="left: {{ ($band['start'] / $timelineMonths) * 100 }}%; width: {{ (($band['end'] - $band['start']) / $timelineMonths) * 100 }}%;"
                                >
                                    {{ $band['label'] }}
                                </div>
                            @endforeach

                            @foreach ($scaleTicks as $tick)
                                @php
                                    $tickPosition = ($tick['months'] / $timelineMonths) * 100;
                                @endphp
                                <div class="absolute inset-y-0 w-px bg-slate-200 dark:bg-zinc-800" style="left: {{ $tickPosition }}%;"></div>
                                <div class="absolute bottom-2 -translate-x-1/2 text-center text-[11px] font-semibold normal-case tracking-normal text-slate-600 dark:text-zinc-300" style="left: {{ $tickPosition }}%;">
                                    {{ $tick['label'] }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @forelse ($timeline as $row)
                    <div class="relative grid min-h-36 grid-cols-[260px_1fr] bg-white hover:z-30 focus-within:z-30 dark:bg-zinc-900">
                        <div class="{{ $row['indication_class'] }} flex flex-col justify-center border-r border-slate-200 px-5 py-4 dark:border-zinc-800">
                            <h3 class="text-sm font-semibold text-slate-950">{{ $row['name'] }}</h3>
                            <p class="mt-1 line-clamp-2 text-[11px] font-semibold uppercase tracking-wide text-slate-700">{{ $row['indication_label'] }}</p>
                            @if (! empty($row['version_name']))
                                <p class="mt-1 text-[11px] text-slate-700">{{ $row['version_name'] }}</p>
                            @endif
                            <a href="{{ route('children.timeline', ['child' => $child, 'vaccine' => $row['code']]) }}" class="mt-3 w-fit rounded bg-white/85 px-2 py-1 text-[11px] font-semibold text-teal-800 ring-1 ring-slate-300 hover:bg-white">Focus</a>
                        </div>

                        <div class="relative px-8 py-7">
                            <div class="absolute inset-x-8 top-1/2 h-3 -translate-y-1/2 rounded-full bg-slate-100 ring-1 ring-slate-200 dark:bg-zinc-800 dark:ring-zinc-700"></div>
                            @foreach ($scaleTicks as $tick)
                                <div class="absolute inset-y-0 w-px bg-slate-100 dark:bg-zinc-800" style="left: calc(2rem + (100% - 4rem) * {{ $tick['months'] / $timelineMonths }});"></div>
                            @endforeach

                            @foreach ($row['doses'] as $point)
                                @php
                                    $statusClasses = match ($point['status']) {
                                        'given' => 'bg-emerald-500 text-white ring-emerald-700/20',
                                        'pending' => 'bg-amber-400 text-slate-950 ring-amber-700/20',
                                        'overdue' => 'bg-red-500 text-white ring-red-700/20',
                                        default => 'bg-sky-400 text-slate-950 ring-sky-700/20',
                                    };
                                    $statusLabel = match ($point['status']) {
                                        'given' => 'Given',
                                        'pending' => 'Pending verification',
                                        'overdue' => 'Overdue',
                                        default => 'Upcoming',
                                    };
                                    $markerText = match ($point['status']) {
                                        'given' => 'G',
                                        'pending' => 'P',
                                        'overdue' => 'D',
                                        default => 'U',
                                    };
                                @endphp

                                <div class="absolute top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 hover:z-40 focus-within:z-40" style="left: calc(2rem + (100% - 4rem) * {{ $point['position'] / 100 }});">
                                    <div class="group relative flex flex-col items-center gap-1">
                                        <div class="relative flex h-11 min-w-14 items-center justify-center rounded-lg border border-slate-400 px-2 text-[10px] font-bold uppercase text-slate-900 shadow-sm {{ $point['indication_class'] }}">
                                            D{{ $point['dose'] }}
                                            <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[9px] font-extrabold ring-2 ring-white dark:ring-zinc-900 {{ $statusClasses }}">
                                                {{ $markerText }}
                                            </span>
                                        </div>
                                        <div class="max-w-24 truncate rounded bg-white/90 px-1.5 py-0.5 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-zinc-900/90 dark:text-zinc-200 dark:ring-zinc-700">
                                            {{ $point['age_summary'] }}
                                        </div>

                                        <div class="pointer-events-auto absolute left-1/2 top-16 z-50 hidden w-64 -translate-x-1/2 rounded-lg border border-slate-200 bg-white p-3 text-xs shadow-lg group-hover:block group-focus-within:block hover:block dark:border-zinc-700 dark:bg-zinc-900">
                                            <div class="font-semibold text-slate-950 dark:text-white">Dose {{ $point['dose'] }} - {{ $point['label'] }}</div>
                                            <div class="mt-1 text-slate-500">Due {{ $point['due_at']->format('M d, Y') }}</div>
                                            <div class="mt-1 text-slate-500">Status: {{ $statusLabel }}</div>
                                            <div class="mt-1 text-slate-500">Schedule: {{ $point['indication_label'] }}</div>
                                            @if ($point['record'])
                                                <div class="mt-2 text-slate-600 dark:text-zinc-300">
                                                    Given {{ $point['record']->administered_at->format('M d, Y') }}
                                                </div>
                                                @if ($point['record']->proofPaths() !== [])
                                                    <div class="mt-2 space-y-1">
                                                        @foreach ($point['record']->proofPaths() as $proofPath)
                                                            <div>
                                                                <a
                                                                    href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($proofPath) }}"
                                                                    target="_blank"
                                                                    class="pointer-events-auto text-teal-700 hover:underline dark:text-teal-300"
                                                                >
                                                                    View submitted proof {{ $loop->iteration }}
                                                                </a>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @elseif ($point['action_at'])
                                                <div class="mt-2 text-slate-600 dark:text-zinc-300">
                                                    Action {{ $point['action_at']->format('M d, Y') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-500">No configured schedule rows found.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
