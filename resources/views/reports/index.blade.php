    <div class="app-page">
        <div class="page-heading">
            <div>
                <p class="eyebrow">ADMIN REPORTS</p>
                <h1 class="page-title">Vaccination report</h1>
                <p class="page-subtitle">Generate barangay, vaccine, verification, and source statistics for a selected reporting period.</p>
            </div>

            <a
                href="{{ route('reports.pdf', request()->only(['start_date', 'end_date', 'schedule_version', 'include_aefi'])) }}"
                class="app-button-primary"
                target="_blank"
                rel="noopener"
            >
                Export PDF
            </a>
        </div>

        <section class="app-card p-4">
            <form method="GET" action="{{ route('reports.index') }}" class="grid gap-4 md:grid-cols-[1fr_1fr_1fr_1fr_auto] md:items-end">
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
                <label class="space-y-1.5">
                    <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">Schedule version</span>
                    <select
                        name="schedule_version"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                    >
                        <option value="all" @selected(($scheduleVersionFilter ?? 'all') === 'all')>All versions</option>
                        <option value="unassigned" @selected(($scheduleVersionFilter ?? 'all') === 'unassigned')>Legacy / unspecified</option>
                        @foreach ($scheduleVersionOptions as $version)
                            <option value="{{ $version->id }}" @selected((string) ($scheduleVersionFilter ?? 'all') === (string) $version->id)>
                                {{ $version->name }} ({{ $version->version_code }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                    <input type="checkbox" name="include_aefi" value="1" @checked($includeAefi) class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <span>Include AEFI in printable report</span>
                </label>
                <button type="submit" class="app-button-secondary">Generate</button>
            </form>
        </section>

        <div class="grid gap-4 md:grid-cols-7">
            <x-stat-card label="Barangays" :value="$stats['barangays']" />
            <x-stat-card label="Barangay admins" :value="$stats['barangayAdmins']" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" />
            <x-stat-card label="Children" :value="$stats['children']" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" />
            <x-stat-card label="AEFI" :value="$stats['aefi']" />
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
                                <th class="px-4 py-3 font-medium">Admins</th>
                                <th class="px-4 py-3 font-medium">Nurses</th>
                                <th class="px-4 py-3 font-medium">Children</th>
                                <th class="px-4 py-3 font-medium">Vaccinations</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($barangays as $barangay)
                                <tr class="app-table-row">
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $barangay->name }}</td>
                                    <td>{{ $barangay->barangay_admins_count }}</td>
                                    <td>{{ $barangay->nurses_count }}</td>
                                    <td>{{ $barangay->children_count }}</td>
                                    <td>{{ $barangay->report_vaccinations_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-zinc-500">No barangays yet.</td></tr>
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
                                <th class="px-4 py-3 font-medium">Administered</th>
                                <th class="px-4 py-3 font-medium">AEFI found</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vaccines as $vaccine)
                                <tr class="app-table-row">
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $vaccine->name }}</td>
                                    <td>{{ strtoupper($vaccine->code) }}</td>
                                    <td>{{ $vaccine->report_records_count }}</td>
                                    <td>{{ $vaccine->report_aefi_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-zinc-500">No vaccines yet.</td></tr>
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
                <div class="space-y-3 p-5">
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
                <div class="space-y-3 p-5">
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
                <dl class="grid gap-3 p-5 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Date range</dt>
                        <dd class="font-medium text-slate-950 dark:text-white">{{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Generated</dt>
                        <dd class="font-medium text-slate-950 dark:text-white">{{ $generatedAt->format('M d, Y h:i A') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Schedule version</dt>
                        <dd class="font-medium text-slate-950 dark:text-white">
                            @if (($selectedScheduleVersion ?? null) instanceof \App\Models\VaccineScheduleVersion)
                                {{ $selectedScheduleVersion->name }} ({{ $selectedScheduleVersion->version_code }})
                            @elseif (is_string($selectedScheduleVersion))
                                {{ $selectedScheduleVersion }}
                            @else
                                All versions
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Schedule version usage</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 font-medium">Version</th>
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Records</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($versionCounts as $versionCount)
                            <tr class="app-table-row">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $versionCount->version_name }}</td>
                                <td>{{ strtoupper($versionCount->version_code) }}</td>
                                <td>{{ $versionCount->total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-zinc-500">No vaccination records in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if (! auth()->user()->isSuperAdmin())
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
                                <th class="px-4 py-3 font-medium">Schedule version</th>
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
                                    <td>{{ $record->suggestedScheduleVersion?->version_code ?? 'Legacy / unspecified' }}</td>
                                    <td class="capitalize">{{ str_replace('_', ' ', $record->verification_status) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-zinc-500">No records in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($includeAefi)
            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Recent AEFI reports</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 font-medium">Child</th>
                                <th class="px-4 py-3 font-medium">Barangay</th>
                                <th class="px-4 py-3 font-medium">Vaccine</th>
                                <th class="px-4 py-3 font-medium">Event date</th>
                                <th class="px-4 py-3 font-medium">Severity</th>
                                <th class="px-4 py-3 font-medium">Symptoms</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentAefiReports as $report)
                                <tr class="app-table-row">
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $report->child?->full_name }}</td>
                                    <td>{{ $report->child?->barangay?->name ?? 'Unassigned' }}</td>
                                    <td>{{ $report->vaccineType?->name ?? 'Not linked' }}</td>
                                    <td>{{ $report->event_date?->format('M d, Y') }}</td>
                                    <td class="capitalize">{{ $report->severity }}</td>
                                    <td>{{ $report->symptoms }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-zinc-500">No AEFI reports in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
