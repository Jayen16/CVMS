<div class="app-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">AEFI reports</h1>
            <p class="page-subtitle">Monitor adverse events after immunization across encoded child records.</p>
        </div>
        <a href="{{ route('aefi-reports.csv') }}" class="app-button-secondary inline-flex items-center gap-2" aria-label="Export AEFI data for Excel as CSV">
            <flux:icon.arrow-down-tray class="size-4" />
            <span>Export Excel</span>
        </a>
    </div>

    <section class="app-card">
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">Child</th>
                        <th class="px-4 py-3 font-medium">Barangay</th>
                        <th class="px-4 py-3 font-medium">Event date</th>
                        <th class="px-4 py-3 font-medium">Severity</th>
                        <th class="px-4 py-3 font-medium">Symptoms</th>
                        <th class="px-4 py-3 font-medium">Reported by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr class="app-table-row">
                            <td><a href="{{ route('children.show', $report->child) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300" wire:navigate>{{ $report->child->full_name }}</a></td>
                            <td>{{ $report->child->barangay?->name }}</td>
                            <td>{{ $report->event_date->format('M d, Y') }}</td>
                            <td>{{ ucfirst($report->severity) }}</td>
                            <td>{{ $report->symptoms }}</td>
                            <td>{{ $report->reporter->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No AEFI reports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $reports->links() }}</div>
    </section>
</div>
