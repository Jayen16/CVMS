    <div class="app-page">
        @if (session('status'))
            <div class="app-alert-success">{{ session('status') }}</div>
        @endif

        <div class="page-heading">
            <div>
                <p class="eyebrow">Administration</p>
                <h1 class="page-title">Vaccine schedule rules</h1>
                <p class="page-subtitle">Manage schedule versions, vaccines, and dose timing used by next-dose suggestions, reminders, and timeline markers.</p>
            </div>
            <a href="{{ route('vaccine-schedules.create') }}" class="app-button-primary">Add vaccine or dose rule</a>
        </div>

        <section class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
            <div class="app-card">
                <div class="app-card-header">
                    <div>
                        <h2 class="app-card-title">Schedule versions</h2>
                        <p class="page-subtitle">A child who already started a vaccine series keeps that series on its assigned version. New untouched series follow the active version.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Effective</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($versions as $version)
                                <tr class="app-table-row">
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $version->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $version->version_code }}</div>
                                    </td>
                                    <td>{{ $version->effective_date?->format('M d, Y') ?? 'Not set' }}</td>
                                    <td>
                                        <span class="status-pill {{ $version->status === 'active' ? 'status-verified' : ($version->status === 'draft' ? '' : 'status-rejected') }}">
                                            {{ str($version->status)->headline() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('vaccine-schedules.index', ['version' => $version->id]) }}" class="app-button-secondary !px-3 !py-1.5 !text-xs">
                                                {{ $selectedVersionId === $version->id ? 'Viewing' : 'View' }}
                                            </a>
                                            @if ($version->status !== 'active')
                                                <form method="POST" action="{{ route('vaccine-schedule-versions.activate', $version) }}">
                                                    @csrf
                                                    <button class="app-button-secondary !px-3 !py-1.5 !text-xs">Set active</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No schedule versions yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="app-card">
                <div class="app-card-header">
                    <div>
                        <h2 class="app-card-title">Create version</h2>
                        <p class="page-subtitle">Use this to prepare the next year or publish a corrected revision.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('vaccine-schedule-versions.store') }}" class="grid gap-4 p-5">
                    @csrf
                    <x-form-field label="Version name" name="name" />
                    <x-form-field label="Version code" name="version_code" />
                    <x-form-field label="Effective date" name="effective_date" type="date" />

                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-slate-800 dark:text-zinc-100">Clone existing version</span>
                        <select name="clone_from_version_id" class="app-input">
                            <option value="">Start empty</option>
                            @foreach ($versions as $version)
                                <option value="{{ $version->id }}">{{ $version->name }} ({{ $version->version_code }})</option>
                            @endforeach
                        </select>
                    </label>

                    <x-form-field label="Notes" name="notes" type="textarea" />

                    <div class="flex justify-end">
                        <button class="app-button-primary">Create version</button>
                    </div>
                </form>
            </div>
        </section>

        <div class="grid gap-5">
            @php
                $selectedVersion = $versions->firstWhere('id', $selectedVersionId);
            @endphp

            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-300">
                Viewing dose rules for:
                <span class="font-semibold text-slate-950 dark:text-white">
                    {{ $selectedVersion?->name ?? 'No selected version' }}
                </span>
                @if ($selectedVersion)
                    <span>({{ $selectedVersion->version_code }})</span>
                @endif
            </div>

            @foreach ($vaccines as $vaccine)
                <section class="app-card">
                    <div class="app-card-header">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="app-card-title">{{ $vaccine->name }}</h2>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $vaccine->code }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 md:justify-end">
                                <span class="status-pill {{ $vaccine->active ? 'status-verified' : 'status-rejected' }}">
                                    {{ $vaccine->active ? 'Active vaccine' : 'Inactive vaccine' }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $vaccine->schedules->count() }} doses
                                </span>
                                <form method="POST" action="{{ route('vaccine-types.toggle', $vaccine) }}">
                                    @csrf
                                    <button class="app-button-secondary !px-3 !py-1.5 !text-xs">{{ $vaccine->active ? 'Deactivate vaccine' : 'Activate vaccine' }}</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="app-table">
                            <thead>
                                <tr>
                                    <th>Dose</th>
                                    <th>Due age</th>
                                    <th>Label</th>
                                    <th>Indication</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($vaccine->schedules as $schedule)
                                    <tr class="app-table-row">
                                        <td class="font-semibold text-slate-950 dark:text-white">Dose {{ $schedule->dose_number }}</td>
                                        <td>{{ $schedule->ageSummary() }}</td>
                                        <td>
                                            {{ $schedule->label }}
                                            @if ($schedule->notes)
                                                <div class="text-xs text-slate-500">{{ $schedule->notes }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <span class="{{ $schedule->indicationClass() }} size-7 rounded border border-slate-300"></span>
                                                <span class="text-sm">{{ $schedule->indicationLabel() }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-pill {{ $schedule->active ? 'status-verified' : 'status-rejected' }}">
                                                {{ $schedule->active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('vaccine-schedules.edit', $schedule) }}" class="app-button-secondary !px-3 !py-1.5 !text-xs">Edit</a>
                                                <form method="POST" action="{{ route('vaccine-schedules.toggle', $schedule) }}">
                                                    @csrf
                                                    <button class="app-button-secondary !px-3 !py-1.5 !text-xs">{{ $schedule->active ? 'Deactivate' : 'Activate' }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No dose rules yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
