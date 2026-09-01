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
    $desktopPhaseWidths = [
        ['start' => 0, 'end' => 12, 'width' => 40],
        ['start' => 12, 'end' => 24, 'width' => 18],
        ['start' => 24, 'end' => 216, 'width' => 42],
    ];
    $mobileStatusClasses = [
        'given' => 'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-900',
        'pending' => 'bg-amber-100 text-amber-900 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-900',
        'overdue' => 'bg-red-100 text-red-800 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900',
        'upcoming' => 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-950 dark:text-sky-200 dark:ring-sky-900',
    ];
    $desktopPosition = static function (float $months) use ($desktopPhaseWidths): float {
        $offset = 0.0;

        foreach ($desktopPhaseWidths as $segment) {
            $segmentSpan = $segment['end'] - $segment['start'];

            if ($months <= $segment['end']) {
                $progress = $segmentSpan > 0 ? max(0, $months - $segment['start']) / $segmentSpan : 0;

                return $offset + ($progress * $segment['width']);
            }

            $offset += $segment['width'];
        }

        return 100.0;
    };
@endphp

<div class="app-page">
    <div class="page-heading xl:items-start">
        <div>
            <a href="{{ route('children.show', $child) }}" class="text-sm text-teal-700 hover:underline dark:text-teal-300">Back to profile</a>
            <p class="eyebrow mt-3">Schedule view</p>
            <h1 class="page-title">{{ $child->full_name }} vaccination timeline</h1>
        </div>

        <form method="GET" action="{{ route('children.timeline', $child) }}" class="app-panel flex w-full flex-wrap items-end gap-3 sm:w-auto" x-data="{ loading: false }" @submit="loading = true">
            <label class="grid gap-2 text-sm">
                <span class="font-medium text-slate-800 dark:text-zinc-100">Vaccine</span>
                <select name="vaccine" class="app-input min-w-72">
                    <option value="">All configured vaccines</option>
                    @foreach ($vaccines as $vaccine)
                        <option value="{{ $vaccine->code }}" @selected($selectedVaccine === $vaccine->code)>{{ $vaccine->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="app-button-primary inline-flex items-center gap-2" :disabled="loading"><span x-show="loading" x-cloak class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span><span x-text="loading ? 'Loading…' : 'View'"></span></button>
            <a href="{{ route('children.timeline.csv', ['child' => $child, 'vaccine' => $selectedVaccine]) }}" class="app-button-secondary inline-flex items-center gap-2" aria-label="Export timeline data for Excel as CSV">
                <flux:icon.arrow-down-tray class="size-4" />
                <span>Export Excel</span>
            </a>
            <a href="{{ route('children.timeline.pdf', ['child' => $child, 'vaccine' => $selectedVaccine]) }}" class="app-button-secondary inline-flex items-center gap-2" target="_blank" rel="noopener" aria-label="Print timeline as PDF">
                <flux:icon.printer class="size-4" />
                <span>Print PDF</span>
            </a>
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

        <div class="px-4 pb-4 pt-2 sm:px-5 sm:pb-5">
            <div class="lg:hidden">
                <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-zinc-400">Age phases</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($phaseBands as $band)
                            <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] ring-1 ring-inset ring-white/60 {{ $band['class'] }}">
                                {{ $band['label'] }}
                            </span>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs leading-5 text-slate-500 dark:text-zinc-400">Tap each vaccine card below for an easier dose-by-dose view on smaller screens.</p>
                </div>

                <div class="space-y-4">
                    @forelse ($timeline as $row)
                        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="{{ $row['indication_class'] }} border-b border-slate-200 px-4 py-4 dark:border-zinc-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-base font-semibold text-slate-950">{{ $row['name'] }}</h3>
                                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700">{{ $row['indication_label'] }}</p>
                                        @if (! empty($row['version_name']))
                                            <p class="mt-1 text-xs text-slate-700">{{ $row['version_name'] }}</p>
                                        @endif
                                    </div>
                                    <a href="{{ route('children.timeline', ['child' => $child, 'vaccine' => $row['code']]) }}" class="shrink-0 rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-teal-800 ring-1 ring-slate-300 hover:bg-white">Focus</a>
                                </div>
                            </div>

                            <div class="p-4">
                                <div class="grid gap-3">
                                    @foreach ($row['doses'] as $point)
                                        @php
                                            $statusLabel = match ($point['status']) {
                                                'given' => 'Given',
                                                'pending' => 'Pending verification',
                                                'overdue' => 'Overdue',
                                                default => 'Upcoming',
                                            };
                                        @endphp
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-zinc-700 dark:bg-zinc-950/80">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-900 ring-1 ring-slate-300 {{ $point['indication_class'] }}">
                                                            D{{ $point['dose'] }}
                                                        </span>
                                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $mobileStatusClasses[$point['status']] ?? $mobileStatusClasses['upcoming'] }}">
                                                            {{ $statusLabel }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $point['label'] }}</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-zinc-400">Age</p>
                                                    <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $point['age_summary'] }}</p>
                                                </div>
                                            </div>

                                            <div class="mt-3 grid gap-2 text-xs text-slate-600 dark:text-zinc-300">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span>Due date</span>
                                                    <span class="text-right font-medium text-slate-900 dark:text-white">{{ $point['due_at']->format('M d, Y') }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-3">
                                                    <span>Schedule</span>
                                                    <span class="text-right font-medium text-slate-900 dark:text-white">{{ $point['indication_label'] }}</span>
                                                </div>
                                                @if ($point['record'])
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span>Given date</span>
                                                        <span class="text-right font-medium text-slate-900 dark:text-white">{{ $point['record']->administered_at->format('M d, Y') }}</span>
                                                    </div>
                                                    @if ($point['record']->proofPaths() !== [])
                                                        <div class="pt-1">
                                                            @foreach ($point['record']->proofPaths() as $proofPath)
                                                                <div class="@if (! $loop->first) mt-1 @endif">
                                                                    <a
                                                                        href="{{ route('vaccinations.proofs.show', ['record' => $point['record'], 'proofIndex' => $loop->iteration]) }}"
                                                                        target="_blank"
                                                                        class="text-teal-700 hover:underline dark:text-teal-300"
                                                                    >
                                                                        View submitted proof {{ $loop->iteration }}
                                                                    </a>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @elseif ($point['action_at'])
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span>Action date</span>
                                                        <span class="text-right font-medium text-slate-900 dark:text-white">{{ $point['action_at']->format('M d, Y') }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-500">No configured schedule rows found.</div>
                    @endforelse
                </div>
            </div>

            <div class="hidden lg:block">
                <div class="overflow-x-auto pb-2" data-checklist-scroll-top>
                    <div class="h-1 min-w-[2160px]" data-checklist-scroll-spacer></div>
                </div>

                <div
                    data-checklist-scroll-bottom
                    @class([
                        'overflow-x-auto overflow-y-visible',
                        'pb-24' => $selectedVaccine !== '',
                    ])
                >
                    <div class="min-w-[2160px] divide-y divide-slate-200 dark:divide-zinc-800">
                        <div class="sticky top-0 z-20 grid grid-cols-[300px_1fr] bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 shadow-sm dark:bg-zinc-950 dark:text-zinc-400">
                            <div class="border-r border-slate-200 px-5 py-3 dark:border-zinc-800">Vaccine</div>
                            <div class="relative px-8 py-3">
                                <div class="relative h-28 rounded-xl border border-slate-200 bg-white/80 dark:border-zinc-800 dark:bg-zinc-900/80">
                                    @foreach ($phaseBands as $band)
                                        <div
                                            class="absolute top-0 flex h-9 items-center justify-center border-b border-slate-200 px-2 text-center text-[10px] font-bold uppercase tracking-[0.22em] dark:border-zinc-800 {{ $band['class'] }}"
                                            style="left: {{ $desktopPosition($band['start']) }}%; width: {{ $desktopPosition($band['end']) - $desktopPosition($band['start']) }}%;"
                                        >
                                            {{ $band['label'] }}
                                        </div>
                                    @endforeach

                                    @foreach ($scaleTicks as $tick)
                                        @php
                                            $tickPosition = $desktopPosition($tick['months']);
                                            $labelRowClass = match (true) {
                                                $tick['months'] <= 4 => match ($loop->index % 3) {
                                                    0 => 'top-11',
                                                    1 => 'top-[4.1rem]',
                                                    default => 'top-[5.2rem]',
                                                },
                                                $tick['months'] <= 24 => $loop->index % 2 === 0 ? 'top-[3.45rem]' : 'top-[5rem]',
                                                default => 'top-[4.3rem]',
                                            };
                                        @endphp
                                        <div class="absolute inset-y-0 w-px bg-slate-200 dark:bg-zinc-800" style="left: {{ $tickPosition }}%;"></div>
                                        <div class="absolute -translate-x-1/2 whitespace-nowrap text-center text-[10px] font-semibold normal-case tracking-normal text-slate-600 dark:text-zinc-300 {{ $labelRowClass }}" style="left: {{ $tickPosition }}%;">
                                            {{ $tick['label'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        @forelse ($timeline as $row)
                            <div class="relative grid min-h-36 grid-cols-[300px_1fr] bg-white hover:z-30 focus-within:z-30 dark:bg-zinc-900">
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
                                        <div class="absolute inset-y-0 w-px bg-slate-100 dark:bg-zinc-800" style="left: calc(2rem + (100% - 4rem) * {{ $desktopPosition($tick['months']) / 100 }});"></div>
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
                                            $desktopPointPosition = $desktopPosition(($point['position'] / 100) * $timelineMonths);
                                        @endphp

                                        <div class="absolute top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 hover:z-40 focus-within:z-40" style="left: calc(2rem + (100% - 4rem) * {{ $desktopPointPosition / 100 }});">
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
                                                                            href="{{ route('vaccinations.proofs.show', ['record' => $point['record'], 'proofIndex' => $loop->iteration]) }}"
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
            </div>
        </div>
    </section>
</div>

<script>
    (() => {
        const topScroller = document.querySelector('[data-checklist-scroll-top]');
        const bottomScroller = document.querySelector('[data-checklist-scroll-bottom]');
        const spacer = document.querySelector('[data-checklist-scroll-spacer]');

        if (!topScroller || !bottomScroller || !spacer) {
            return;
        }

        let syncing = false;

        const syncWidth = () => {
            const content = bottomScroller.firstElementChild;
            if (!content) {
                return;
            }

            spacer.style.width = `${content.scrollWidth}px`;
        };

        const syncScroll = (source, target) => {
            if (syncing) {
                return;
            }

            syncing = true;
            target.scrollLeft = source.scrollLeft;
            requestAnimationFrame(() => {
                syncing = false;
            });
        };

        syncWidth();
        window.addEventListener('resize', syncWidth);
        topScroller.addEventListener('scroll', () => syncScroll(topScroller, bottomScroller), { passive: true });
        bottomScroller.addEventListener('scroll', () => syncScroll(bottomScroller, topScroller), { passive: true });
    })();
</script>
