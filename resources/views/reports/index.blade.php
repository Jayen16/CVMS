<x-layouts::app :title="__('Reports')">
    <div class="app-page">
        <div class="page-heading">
            <div>
                <p class="eyebrow">ADMIN REPORTS</p>
                <h1 class="page-title">Vaccination report</h1>
                <p class="page-subtitle">Generate barangay, vaccine, verification, and source statistics for a selected reporting period.</p>
            </div>

            <a
                href="{{ route('reports.pdf', request()->only(['start_date', 'end_date'])) }}"
                class="app-button-primary"
                target="_blank"
                rel="noopener"
            >
                Export PDF
            </a>
        </div>

        <section class="app-card">
            <form method="GET" action="{{ route('reports.index') }}" class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <label class="space-y-1.5">
                    <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">Start date</span>
                    <input
                        type="date"
                        name="start_date"
                        value="{{ request('start_date', $startDate->toDateString()) }}"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                    >
                </label>
                <label class="space-y-1.5">
                    <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">End date</span>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ request('end_date', $endDate->toDateString()) }}"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                    >
                </label>
                <button type="submit" class="app-button-secondary">Generate</button>
            </form>
        </section>

        <div class="grid gap-4 md:grid-cols-5">
            <x-stat-card label="Barangays" :value="$stats['barangays']" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" />
            <x-stat-card label="Children" :value="$stats['children']" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" />
            <x-stat-card label="Pending review" :value="$stats['pending']" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Barangay coverage</h2>
                    <p class="text-sm text-zinc-500">{{ $startDate->format('M d, Y') }} to {{ $endDate->format('M d, Y') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 font-medium">Barangay</th>
                                <th class="px-4 py-3 font-medium">Nurses</th>
                                <th class="px-4 py-3 font-medium">Children</th>
                                <th class="px-4 py-3 font-medium">Vaccinations</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($barangays as $barangay)
                                <tr class="app-table-row">
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $barangay->name }}</td>
                                    <td>{{ $barangay->nurses_count }}</td>
                                    <td>{{ $barangay->children_count }}</td>
                                    <td>{{ $barangay->report_vaccinations_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-zinc-500">No barangays yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Vaccines administered</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 font-medium">Vaccine</th>
                                <th class="px-4 py-3 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vaccines as $vaccine)
                                <tr class="app-table-row">
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $vaccine->name }}</td>
                                    <td>{{ strtoupper($vaccine->code) }}</td>
                                    <td>{{ $vaccine->report_records_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-center text-zinc-500">No vaccines yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Verification status</h2>
                </div>
                <div class="space-y-3 p-5 pt-0">
                    @forelse ($verificationCounts as $status => $total)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3 dark:bg-zinc-900">
                            <span class="text-sm font-medium capitalize text-slate-700 dark:text-zinc-200">{{ str_replace('_', ' ', $status) }}</span>
                            <span class="text-lg font-semibold text-slate-950 dark:text-white">{{ $total }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">No vaccination records in this period.</p>
                    @endforelse
                </div>
            </section>

            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Record source</h2>
                </div>
                <div class="space-y-3 p-5 pt-0">
                    @forelse ($sourceCounts as $source => $total)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3 dark:bg-zinc-900">
                            <span class="text-sm font-medium capitalize text-slate-700 dark:text-zinc-200">{{ str_replace('_', ' ', $source) }}</span>
                            <span class="text-lg font-semibold text-slate-950 dark:text-white">{{ $total }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">No vaccination records in this period.</p>
                    @endforelse
                </div>
            </section>

            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Report details</h2>
                </div>
                <dl class="grid gap-3 p-5 pt-0 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Date range</dt>
                        <dd class="font-medium text-slate-950 dark:text-white">{{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Generated</dt>
                        <dd class="font-medium text-slate-950 dark:text-white">{{ $generatedAt->format('M d, Y h:i A') }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Recent vaccination records</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 font-medium">Child</th>
                            <th class="px-4 py-3 font-medium">Barangay</th>
                            <th class="px-4 py-3 font-medium">Vaccine</th>
                            <th class="px-4 py-3 font-medium">Dose</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentRecords as $record)
                            <tr class="app-table-row">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $record->child?->full_name }}</td>
                                <td>{{ $record->child?->barangay?->name ?? 'Unassigned' }}</td>
                                <td>{{ $record->vaccineType?->name }}</td>
                                <td>{{ $record->dose_number ? 'Dose '.$record->dose_number : 'Not set' }}</td>
                                <td>{{ $record->administered_at?->format('M d, Y') }}</td>
                                <td class="capitalize">{{ str_replace('_', ' ', $record->verification_status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-zinc-500">No records in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
