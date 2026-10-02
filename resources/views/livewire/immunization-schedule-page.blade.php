<div class="app-page">
    <div wire:loading.flex class="fixed inset-x-0 top-0 z-[60] items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Filtering data…
    </div>
    <div class="page-heading"><div><p class="eyebrow">ROUTINE IMMUNIZATION</p><h1 class="page-title">Schedule monitoring</h1><p class="page-subtitle">Review each child’s next schedule item, timing status, missed-dose risk, and follow-up channel.</p></div>@unless($requiresLocationSelection)<a href="{{ route('schedule-monitoring.pdf', ['search' => $search, 'status' => $status, 'risk' => $risk, 'regionId' => $regionId, 'provinceId' => $provinceId, 'municipalityId' => $municipalityId, 'barangayId' => $barangayId]) }}" target="_blank" rel="noopener" class="app-button-secondary inline-flex items-center gap-2" aria-label="Print schedule monitoring as PDF"><flux:icon.printer class="size-4" /> Print PDF</a>@endunless</div>
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
            <h2 class="app-card-title">Select a region to view schedule monitoring</h2>
            <p class="mt-2 text-sm text-zinc-500">Choose a location above to load children, vaccination history, and missed-dose risk for that area.</p>
        </section>
    @else
    <section class="app-card mb-6 p-4"><div class="grid gap-3 md:grid-cols-[1fr_180px_180px_auto] md:items-end">
        <label class="space-y-1.5"><span class="text-sm font-medium">Search child</span><input wire:model.live.debounce.300ms="search" class="app-input w-full" placeholder="Name" /></label>
        <label class="space-y-1.5"><span class="text-sm font-medium">Schedule status</span><select wire:model.live="status" class="app-input w-full"><option value="all">All statuses</option><option value="overdue">Overdue</option><option value="delayed">Delayed</option><option value="due">Due today</option><option value="upcoming">Upcoming</option><option value="complete">Complete</option></select></label>
        <label class="space-y-1.5"><span class="text-sm font-medium">Risk level</span><select wire:model.live="risk" class="app-input w-full"><option value="all">All risk levels</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option><option value="not_applicable">Not applicable</option></select></label>
        <button type="button" wire:click="$set('search', '')" class="app-button-secondary">Clear</button>
    </div></section>
    <div
        x-data="{
            current: 0,
            count: 2,
            next() { this.current = (this.current + 1) % this.count },
            previous() { this.current = (this.current - 1 + this.count) % this.count },
        }"
        class="schedule-monitoring-chart-carousel"
        aria-label="Schedule monitoring charts"
    >
        <div class="schedule-monitoring-chart-carousel__track">
            <div class="schedule-monitoring-chart-carousel__slide" :class="{ 'is-active': current === 0 }">
                <x-dashboard-bar-chart title="Schedule status" subtitle="Children grouped by their next schedule status." orientation="horizontal" :data="$statusMonitorChart" />
            </div>
            <div class="schedule-monitoring-chart-carousel__slide" :class="{ 'is-active': current === 1 }">
                <x-dashboard-pie-chart title="Missed-dose risk" subtitle="Risk distribution for the matching children." :data="$riskChart" />
            </div>
        </div>
        <div class="schedule-monitoring-chart-carousel__controls">
            <button type="button" class="app-button-secondary" x-on:click="previous" aria-label="Show previous chart">
                <span aria-hidden="true">&larr;</span>
                <span>Previous</span>
            </button>
            <span class="text-xs font-medium text-slate-500 dark:text-zinc-400" aria-live="polite">
                Chart <span x-text="current + 1"></span> of <span x-text="count"></span>
            </span>
            <button type="button" class="app-button-secondary" x-on:click="next" aria-label="Show next chart">
                <span>Next</span>
                <span aria-hidden="true">&rarr;</span>
            </button>
        </div>
    </div>
    <section class="app-card overflow-hidden"><div class="app-card-header flex flex-wrap items-center justify-between gap-3"><div><h2 class="app-card-title">Child schedule checklist</h2><p class="mt-1 text-sm text-zinc-500">{{ $rows->count() }} of {{ $totalMatching }} matching children shown</p></div><div class="flex flex-wrap items-center gap-2"><label class="flex items-center gap-2 text-sm font-medium">Rows per page <select wire:model.live="perPage" class="app-input !w-auto"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label><a href="{{ route('reminder-history.index', ['regionId' => $regionId, 'provinceId' => $provinceId, 'municipalityId' => $municipalityId, 'barangayId' => $barangayId]) }}" class="app-button-secondary">Reminder history</a><a href="{{ route('predictive-analytics.index', ['regionId' => $regionId, 'provinceId' => $provinceId, 'municipalityId' => $municipalityId, 'barangayId' => $barangayId]) }}" class="app-button-secondary">View analytics</a></div></div>
        <div class="grid gap-3 p-3 md:hidden">
        @forelse ($rows as $row)
            @php $statusClasses = ['overdue' => 'status-rejected', 'delayed' => 'bg-orange-100 text-orange-800', 'due' => 'bg-yellow-100 text-yellow-800', 'upcoming' => 'bg-sky-100 text-sky-800', 'complete' => 'status-verified']; $riskClasses = ['high' => 'status-rejected', 'medium' => 'bg-orange-100 text-orange-800', 'low' => 'status-verified', 'not_applicable' => 'bg-zinc-100 text-zinc-600']; $suggestion = $row['suggestion']; @endphp
            <article class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                <div class="flex items-start justify-between gap-3"><div class="min-w-0"><a href="{{ route('children.show', $row['child']) }}" class="font-semibold text-teal-700 hover:underline">{{ $row['child']->full_name }}</a><p class="mt-0.5 text-xs text-zinc-500">{{ $row['child']->barangay?->name ?? 'No barangay' }}</p></div><span class="status-pill {{ $statusClasses[$row['status']] ?? 'status-verified' }}">{{ str($row['status'])->replace('_', ' ')->headline() }}</span></div>
                <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-slate-100 pt-3 text-sm dark:border-zinc-800">
                    <div class="col-span-2"><dt class="text-xs text-zinc-500">Next due vaccine</dt><dd class="mt-0.5 font-medium">@if ($suggestion['due_at']){{ $suggestion['vaccine_name'] }} dose {{ $suggestion['dose_number'] }}<span class="block text-xs text-zinc-500">{{ $suggestion['due_label'] }}</span>@else Schedule complete @endif</dd></div>
                    <div><dt class="text-xs text-zinc-500">Due date</dt><dd class="mt-0.5">{{ $suggestion['due_at']?->format('M d, Y') ?? '—' }} @if ($row['days_late'] > 0)<span class="block text-xs text-red-600">{{ $row['days_late'] }} days late</span>@endif</dd></div>
                    <div><dt class="text-xs text-zinc-500">Records</dt><dd class="mt-0.5">{{ $row['child']->vaccinations_count }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-zinc-500">Missed-dose risk</dt><dd class="mt-0.5"><span class="status-pill {{ $riskClasses[$row['risk_level']] ?? 'bg-zinc-100 text-zinc-600' }}">{{ str($row['risk_level'])->replace('_', ' ')->headline() }}@if ($row['risk']) ({{ $row['risk']['risk_probability'] }}%) @endif</span>@if ($row['risk'])<p class="mt-1 text-xs text-zinc-500">{{ implode('; ', $row['risk']['reasons']) }}</p>@endif</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-zinc-500">Follow-up</dt><dd class="mt-0.5 font-medium">{{ $row['contact_channel'] }}</dd><p class="text-xs text-zinc-500">SMS is prioritized when available</p></div>
                </dl>
                <div class="mt-3 border-t border-slate-100 pt-3 dark:border-zinc-800"><a href="{{ route('children.timeline', $row['child']) }}" class="text-sm font-semibold text-teal-700 hover:underline">View timeline</a></div>
            </article>
        @empty <div class="app-card p-6 text-center text-sm text-zinc-500">No children match the selected filters.</div>@endforelse
        </div>
        <div class="hidden overflow-x-auto md:block"><table class="app-table w-full {{ $rows->isNotEmpty() ? 'min-w-[1050px]' : '' }}"><thead><tr><th>Child / barangay</th><th>Next due vaccine</th><th>Due date</th><th>Status</th><th>Missed-dose risk</th><th>Follow-up</th><th>Records</th><th></th></tr></thead><tbody>
        @forelse ($rows as $row)
            @php $statusClasses = ['overdue' => 'status-rejected', 'delayed' => 'bg-orange-100 text-orange-800', 'due' => 'bg-yellow-100 text-yellow-800', 'upcoming' => 'bg-sky-100 text-sky-800', 'complete' => 'status-verified']; $riskClasses = ['high' => 'status-rejected', 'medium' => 'bg-orange-100 text-orange-800', 'low' => 'status-verified', 'not_applicable' => 'bg-zinc-100 text-zinc-600']; $suggestion = $row['suggestion']; @endphp
            <tr class="app-table-row align-top"><td><a href="{{ route('children.show', $row['child']) }}" class="font-semibold text-teal-700 hover:underline">{{ $row['child']->full_name }}</a><div class="text-xs text-zinc-500">{{ $row['child']->barangay?->name ?? 'No barangay' }}</div></td><td>@if ($suggestion['due_at'])<span class="font-medium">{{ $suggestion['vaccine_name'] }} dose {{ $suggestion['dose_number'] }}</span><div class="text-xs text-zinc-500">{{ $suggestion['due_label'] }}</div>@else<span class="text-zinc-500">Schedule complete</span>@endif</td><td>{{ $suggestion['due_at']?->format('M d, Y') ?? '—' }} @if ($row['days_late'] > 0)<div class="text-xs text-red-600">{{ $row['days_late'] }} days late</div>@endif</td><td><span class="status-pill {{ $statusClasses[$row['status']] ?? 'status-verified' }}">{{ str($row['status'])->replace('_', ' ')->headline() }}</span></td><td><span class="status-pill {{ $riskClasses[$row['risk_level']] ?? 'bg-zinc-100 text-zinc-600' }}">{{ str($row['risk_level'])->replace('_', ' ')->headline() }}@if ($row['risk']) ({{ $row['risk']['risk_probability'] }}%) @endif</span>@if ($row['risk'])<div class="mt-1 max-w-64 text-xs text-zinc-500">{{ implode('; ', $row['risk']['reasons']) }}</div>@endif</td><td><span class="font-medium">{{ $row['contact_channel'] }}</span><div class="text-xs text-zinc-500">SMS is prioritized when available</div></td><td>{{ $row['child']->vaccinations_count }}</td><td><a href="{{ route('children.timeline', $row['child']) }}" class="text-sm font-semibold text-teal-700 hover:underline">Timeline</a></td></tr>
        @empty <tr><td colspan="8" class="!px-4 !py-10 text-center text-zinc-500">No children match the selected filters.</td></tr>@endforelse
        </tbody></table></div>
        @if ($rows->hasPages())
            <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">{{ $rows->links() }}</div>
        @endif
    </section>
    @endif
</div>
