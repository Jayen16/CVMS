<div class="app-page">
    <div wire:loading.flex class="fixed inset-x-0 top-0 z-[60] items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Filtering data…
    </div>
    <div class="page-heading">
        <div>
            <p class="eyebrow">ADVISORY ANALYTICS</p>
            <h1 class="page-title">Vaccine demand forecast and inventory planning</h1>
            <p class="page-subtitle">Compare historical-data demand estimates with current stock to support RHU planning. These figures do not make clinical or procurement decisions.</p>
        </div>
    </div>

    <div class="space-y-4">
        @if (auth()->user()->isSuperAdmin() || auth()->user()->isMunicipalAdmin())
            <x-location-filters
                mode="wire"
                :regions="$regions"
                :provinces="$provinces"
                :municipalities="$municipalities"
                :barangays="$barangays"
                :region-value="$regionId"
                :province-value="$provinceId"
                :municipality-value="$municipalityId"
                :barangay-value="$barangayId"
                region-model="regionId"
                province-model="provinceId"
                municipality-model="municipalityId"
                barangay-model="barangayId"
            />
        @endif
        @if ($requiresLocationSelection)
            <section class="app-card p-8 text-center">
                <h2 class="app-card-title">Select a region to view the vaccine demand forecast</h2>
                <p class="mt-2 text-sm text-zinc-500">Choose a location above to calculate demand and inventory projections for that area.</p>
            </section>
        @else
        <section class="app-card border-l-4 border-teal-500 px-4 py-3">
            <p class="text-sm text-zinc-600 dark:text-zinc-300"><span class="font-semibold text-teal-700 dark:text-teal-300">Planning note:</span> Estimated demand combines scheduled doses, catch-up backlog, and historical vaccination activity. Compare it with available stock; a negative projected balance indicates a possible shortage for staff review.</p>
        </section>

        <section class="app-card relative w-full overflow-hidden">
            <div class="app-card-header flex flex-wrap items-start justify-between gap-3">
                <div>
                <h2 class="app-card-title">Estimated vaccine demand</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $forecastMonths }}-month historical-data forecast compared with recorded inventory. Basis: {{ $selectedVersion?->name ?? 'latest active schedule' }}.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3"><label class="flex items-center gap-2 text-sm font-medium">Forecast period <select wire:model.live="months" wire:loading.attr="disabled" class="app-input !w-auto"><option value="1">1 month</option><option value="3">3 months</option><option value="6">6 months</option><option value="12">12 months</option></select></label><label class="flex items-center gap-2 text-sm font-medium">Schedule version <select wire:model.live="scheduleVersion" wire:loading.attr="disabled" class="app-input max-w-64"><option value="">Latest active</option>@foreach ($scheduleVersions as $version)<option value="{{ $version->id }}">{{ $version->name }} ({{ $version->version_code }}){{ $version->status === 'active' ? ' - Active' : '' }}</option>@endforeach</select></label></div>
            </div>
            <div wire:loading.flex class="absolute inset-0 z-20 items-center justify-center bg-white/70 dark:bg-zinc-900/70"><div class="flex items-center gap-2 rounded-lg bg-white px-4 py-3 text-sm font-medium text-zinc-700 shadow dark:bg-zinc-800 dark:text-zinc-200"><span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-teal-700"></span> Recalculating forecast…</div></div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead><tr><th>Vaccine</th><th>Scheduled due</th><th>Catch-up backlog</th><th>Recent history</th><th>Estimated demand</th><th>Available stock</th><th>Projected balance</th></tr></thead>
                    <tbody>
                        @forelse ($demand as $row)
                            <tr class="app-table-row"><td class="font-semibold">{{ $row['vaccine']->name }}</td><td>{{ $row['scheduled_due'] }}</td><td>{{ $row['catch_up_backlog'] }}</td><td>{{ $row['recent_three_months'] }}</td><td class="font-semibold">{{ $row['estimated_demand'] }}</td><td>{{ $row['available_stock'] }}</td><td><span class="status-pill {{ $row['stock_status'] === 'shortage' ? 'status-rejected' : 'status-verified' }}">{{ $row['projected_balance'] >= 0 ? '+' : '' }}{{ $row['projected_balance'] }} ({{ ucfirst($row['stock_status']) }})</span></td></tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No demand data available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        @endif

    </div>
</div>
