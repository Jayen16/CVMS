    <div class="app-page">
        @if (session('status'))
            <div class="app-alert-success">{{ session('status') }}</div>
        @endif

        <div class="page-heading">
            <div>
                <p class="eyebrow">Administration</p>
                <h1 class="page-title">Vaccine schedule rules</h1>
                <p class="page-subtitle">Manage vaccines and dose timing used by the AI next-dose suggestion, reminders, and timeline chart.</p>
            </div>
            <a href="{{ route('vaccine-schedules.create') }}" class="app-button-primary">Add vaccine or dose rule</a>
        </div>

        <div class="grid gap-5">
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
