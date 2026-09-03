<div class="app-page">
    <div class="page-heading">
        <div>
            <p class="eyebrow">ADVISORY ANALYTICS</p>
            <h1 class="page-title">Predictive analytics</h1>
            <p class="page-subtitle">Use these estimates to support staff planning. They do not make clinical or procurement decisions.</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="app-card overflow-hidden">
            <div class="app-card-header">
                <h2 class="app-card-title">Estimated vaccine demand</h2>
                <p class="mt-1 text-sm text-zinc-500">Three-month historical-data estimate with scheduled demand and catch-up backlog.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead><tr><th>Vaccine</th><th>Scheduled due</th><th>Catch-up backlog</th><th>12-month history</th><th>Estimated doses</th></tr></thead>
                    <tbody>
                        @forelse ($demand as $row)
                            <tr class="app-table-row"><td class="font-semibold">{{ $row['vaccine']->name }}</td><td>{{ $row['scheduled_due'] }}</td><td>{{ $row['catch_up_backlog'] }}</td><td>{{ $row['recent_three_months'] }}</td><td class="font-semibold">{{ $row['estimated_demand'] }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No demand data available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="app-card overflow-hidden">
            <div class="app-card-header">
                <h2 class="app-card-title">Missed-dose risk</h2>
                <p class="mt-1 text-sm text-zinc-500">Historical-data risk estimate for follow-up, not a clinical diagnosis.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead><tr><th>Child</th><th>Next dose</th><th>Risk estimate</th><th>Historical basis</th></tr></thead>
                    <tbody>
                        @forelse ($risks as $row)
                            <tr class="app-table-row align-top"><td><a href="{{ route('children.show', $row['child']) }}" class="font-semibold text-teal-700 hover:underline">{{ $row['child']->full_name }}</a><div class="text-xs text-zinc-500">{{ $row['child']->barangay?->name }}</div></td><td>{{ $row['suggestion']['vaccine_name'] }} dose {{ $row['suggestion']['dose_number'] }}<div class="text-xs text-zinc-500">{{ ucfirst($row['suggestion']['status']) }}</div></td><td><span class="status-pill {{ $row['risk_level'] === 'high' ? 'status-rejected' : ($row['risk_level'] === 'medium' ? 'bg-orange-100 text-orange-800' : 'status-verified') }}">{{ ucfirst($row['risk_level']) }} ({{ $row['risk_probability'] }}%)</span></td><td class="text-sm">{{ implode('; ', $row['reasons']) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No actionable missed-dose risks found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
