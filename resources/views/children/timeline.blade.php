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
                    <div class="text-xs font-medium text-slate-500">Birth to 5 years scale</div>
                </div>
            </div>

            <div @class([
                'overflow-x-auto overflow-y-visible',
                'pb-24' => $selectedVaccine !== '',
            ])>
                <div class="min-w-[1180px] divide-y divide-slate-200 dark:divide-zinc-800">
                    <div class="sticky top-0 z-20 grid grid-cols-[260px_1fr] bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 shadow-sm dark:bg-zinc-950 dark:text-zinc-400">
                        <div class="border-r border-slate-200 px-5 py-3 dark:border-zinc-800">Vaccine</div>
                        <div class="relative px-8 py-3">
                            <div class="grid grid-cols-8">
                                <span>Birth</span>
                                <span>6 wks</span>
                                <span>10 wks</span>
                                <span>14 wks</span>
                                <span>6 mos</span>
                                <span>9 mos</span>
                                <span>12 mos</span>
                                <span>5 yrs</span>
                            </div>
                        </div>
                    </div>

                    @forelse ($timeline as $row)
                        <div class="relative grid min-h-32 grid-cols-[260px_1fr] bg-white hover:z-30 focus-within:z-30 dark:bg-zinc-900">
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
                                <div class="absolute inset-y-0 left-[12.5%] w-px bg-slate-100 dark:bg-zinc-800"></div>
                                <div class="absolute inset-y-0 left-[25%] w-px bg-slate-100 dark:bg-zinc-800"></div>
                                <div class="absolute inset-y-0 left-[37.5%] w-px bg-slate-100 dark:bg-zinc-800"></div>
                                <div class="absolute inset-y-0 left-[50%] w-px bg-slate-100 dark:bg-zinc-800"></div>
                                <div class="absolute inset-y-0 left-[62.5%] w-px bg-slate-100 dark:bg-zinc-800"></div>
                                <div class="absolute inset-y-0 left-[75%] w-px bg-slate-100 dark:bg-zinc-800"></div>
                                <div class="absolute inset-y-0 left-[87.5%] w-px bg-slate-100 dark:bg-zinc-800"></div>

                                @foreach ($row['doses'] as $point)
                                    @php
                                        $markerClasses = match ($point['status']) {
                                            'given' => 'border-emerald-600 bg-emerald-500 text-white shadow-emerald-900/20',
                                            'pending' => 'border-amber-500 bg-amber-400 text-slate-950 shadow-amber-900/20',
                                            'overdue' => 'border-red-600 bg-red-500 text-white shadow-red-900/20',
                                            default => 'border-sky-500 bg-sky-400 text-slate-950 shadow-sky-900/20',
                                        };
                                        $statusLabel = match ($point['status']) {
                                            'given' => 'Given',
                                            'pending' => 'Pending verification',
                                            'overdue' => 'Overdue',
                                            default => 'Upcoming',
                                        };
                                        $markerText = match ($point['status']) {
                                            'given' => 'OK',
                                            'pending' => '!',
                                            'overdue' => 'Due',
                                            default => 'Todo',
                                        };
                                    @endphp

                                    <div class="absolute top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 hover:z-40 focus-within:z-40" style="left: calc(2rem + (100% - 4rem) * {{ $point['position'] / 100 }});">
                                        <div class="group relative flex flex-col items-center gap-1">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-md border text-[10px] font-bold shadow-sm {{ $markerClasses }}">
                                                {{ $markerText }}
                                            </div>
                                            <div class="max-w-24 truncate rounded bg-white/90 px-1.5 py-0.5 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-zinc-900/90 dark:text-zinc-200 dark:ring-zinc-700">
                                                D{{ $point['dose'] }} · {{ $point['age_summary'] }}
                                            </div>

                                            <div class="pointer-events-auto absolute left-1/2 top-16 z-50 hidden w-64 -translate-x-1/2 rounded-lg border border-slate-200 bg-white p-3 text-xs shadow-lg group-hover:block group-focus-within:block hover:block dark:border-zinc-700 dark:bg-zinc-900">
                                                <div class="font-semibold text-slate-950 dark:text-white">Dose {{ $point['dose'] }} · {{ $point['label'] }}</div>
                                                <div class="mt-1 text-slate-500">Due {{ $point['due_at']->format('M d, Y') }}</div>
                                                <div class="mt-1 text-slate-500">Status: {{ $statusLabel }}</div>
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

