<x-layouts::app :title="$child->full_name.' timeline'">
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

        <div class="grid gap-4 md:grid-cols-4">
            <div class="app-panel">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Given</div>
                <div class="mt-3 h-2 rounded-full bg-emerald-500"></div>
            </div>
            <div class="app-panel">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending verification</div>
                <div class="mt-3 h-2 rounded-full bg-amber-400"></div>
            </div>
            <div class="app-panel">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Upcoming</div>
                <div class="mt-3 h-2 rounded-full bg-sky-400"></div>
            </div>
            <div class="app-panel">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Overdue</div>
                <div class="mt-3 h-2 rounded-full bg-red-500"></div>
                <div class="mt-2 text-xs text-slate-500">Suggest action today</div>
            </div>
        </div>

        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Schedule indication legend</h2>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($indications as $value => $label)
                    <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                        <span class="schedule-indication-{{ str_replace('_', '-', $value) }} h-9 w-16 rounded border border-slate-300"></span>
                        <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="app-card">
            <div class="app-card-header">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="app-card-title">Routine schedule timeline</h2>
                    <div class="text-xs font-medium text-slate-500">Birth to 5 years scale</div>
                </div>
            </div>

            <div class="max-h-[72vh] overflow-auto">
                <div class="min-w-[1280px] divide-y divide-slate-200 dark:divide-zinc-800">
                    <div class="sticky top-0 z-20 grid grid-cols-[280px_1fr] bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 shadow-sm dark:bg-zinc-950 dark:text-zinc-400">
                        <div class="px-6 py-4">Vaccine</div>
                        <div class="grid grid-cols-6 px-8 py-4">
                            <span>Birth</span>
                            <span>1 mo</span>
                            <span>6 mos</span>
                            <span>12 mos</span>
                            <span>24 mos</span>
                            <span>5 yrs</span>
                        </div>
                    </div>

                    @forelse ($timeline as $row)
                        <div class="grid min-h-44 grid-cols-[280px_1fr] items-center">
                            <div class="px-6 py-8">
                                <div class="font-semibold text-slate-950 dark:text-white">{{ $row['name'] }}</div>
                                <a href="{{ route('children.timeline', ['child' => $child, 'vaccine' => $row['code']]) }}" class="text-xs text-teal-700 hover:underline dark:text-teal-300">Focus</a>
                            </div>
                            <div class="relative mx-8 my-10 h-28 rounded-lg bg-slate-100 ring-1 ring-slate-200 dark:bg-zinc-800 dark:ring-zinc-700">
                                <div class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-slate-300 dark:bg-zinc-600"></div>
                                <div class="absolute inset-y-0 left-[20%] w-px bg-slate-200 dark:bg-zinc-700"></div>
                                <div class="absolute inset-y-0 left-[40%] w-px bg-slate-200 dark:bg-zinc-700"></div>
                                <div class="absolute inset-y-0 left-[60%] w-px bg-slate-200 dark:bg-zinc-700"></div>
                                <div class="absolute inset-y-0 left-[80%] w-px bg-slate-200 dark:bg-zinc-700"></div>

                                @foreach ($row['doses'] as $point)
                                    <div class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2" style="left: {{ $point['position'] }}%">
                                        <div class="group relative flex size-12 items-center justify-center">
                                            <div class="{{ $point['indication_class'] }} absolute inset-0 rounded-full opacity-60"></div>
                                            <div class="relative size-8 rounded-full ring-4 ring-white dark:ring-zinc-800
                                                @if ($point['status'] === 'given') bg-emerald-500
                                                @elseif ($point['status'] === 'pending') bg-amber-400
                                                @elseif ($point['status'] === 'overdue') bg-red-500
                                                @else bg-sky-400 @endif">
                                            </div>
                                            <div class="pointer-events-none absolute left-1/2 top-14 z-30 hidden w-64 -translate-x-1/2 rounded-lg border border-slate-200 bg-white p-3 text-xs shadow-lg group-hover:block dark:border-zinc-700 dark:bg-zinc-900">
                                                <div class="font-semibold text-slate-950 dark:text-white">Dose {{ $point['dose'] }} | {{ $point['label'] }}</div>
                                                <div class="mt-1 text-slate-500">Due {{ $point['due_at']->format('M d, Y') }}</div>
                                                <div class="mt-1 text-slate-500">Indication: {{ $point['indication_label'] }}</div>
                                                @if ($point['record'])
                                                    <div class="mt-2 text-slate-600 dark:text-zinc-300">
                                                        Given {{ $point['record']->administered_at->format('M d, Y') }}
                                                        <br>
                                                        Status: {{ ucfirst($point['record']->verification_status) }}
                                                    </div>
                                                @else
                                                    <div class="mt-2 text-slate-600 dark:text-zinc-300">
                                                        {{ ucfirst($point['status']) }}
                                                        @if ($point['action_at'])
                                                            <br>
                                                            Suggested action: {{ $point['action_at']->format('M d, Y') }}
                                                        @endif
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
</x-layouts::app>
