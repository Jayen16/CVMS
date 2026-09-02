<div class="app-page">
    <div wire:loading.flex class="fixed inset-x-0 top-0 z-[60] items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Filtering data…
    </div>
    @if (session('status'))
        <div class="app-alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="page-heading">
        <div>
            <p class="eyebrow">SYNC</p>
            <h1 class="page-title">Sync data</h1>
            <p class="page-subtitle">Review pending sync items, last completed sync, and run a manual sync when needed.</p>
            @if ($installation)
                <p class="mt-2 text-sm text-zinc-500">Connection:
                    <span class="font-medium {{ $installation->status === 'active' ? 'text-emerald-600' : 'text-rose-600' }}">{{ ucfirst($installation->status) }}</span>
                    @if ($installation->last_synchronized_at)
                        · Last exchange {{ $installation->last_synchronized_at->diffForHumans() }}
                    @endif
                </p>
            @endif
        </div>

        @if (auth()->user()->isBarangayAdmin() && (!$installation || $installation->status === 'active'))
            <form method="POST" action="{{ route('sync.manual') }}">
                @csrf
            <button class="app-button-primary inline-flex items-center gap-2" aria-label="Sync data now">
                <flux:icon.arrow-path class="size-4" />
                <span>Sync now</span>
            </button>
            </form>
        @endif
    </div>

    @unless ($viewAll)
        <div class="grid gap-4 md:grid-cols-3">
            <x-stat-card label="Pending sync" :value="$pendingCount" />
            <x-stat-card label="Last processed" :value="$latestStatus?->last_processed ?? 0" />
            <x-stat-card label="Last failed" :value="$latestStatus?->last_failed ?? 0" />
        </div>
    @endunless

    @if ((auth()->user()->isSuperAdmin() || auth()->user()->isMunicipalAdmin()) && ! $viewAll)
        <x-location-filters mode="wire" :regions="$regions" :provinces="$provinces" :municipalities="$municipalities" :barangays="$barangays" :region-value="$regionFilter" :province-value="$provinceFilter" :municipality-value="$municipalityFilter" :barangay-value="$barangayFilter" />
    @endif

    @unless ($viewAll)
        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Latest sync status</h2>
            </div>
            <dl class="grid gap-3 p-5 text-sm md:grid-cols-2">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Last synced at</dt>
                    <dd class="font-medium text-slate-950 dark:text-white">
                        {{ $latestStatus?->last_synced_at?->format('M d, Y h:i A') ?? 'No completed sync yet' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Synced by</dt>
                    <dd class="font-medium text-slate-950 dark:text-white">
                        {{ $latestStatus?->user?->name ?? 'Not recorded' }}
                    </dd>
                </div>
            </dl>
        </section>
    @endunless

    <section class="app-card">
        <div class="app-card-header">
            <div class="flex items-center justify-between gap-3">
                <h2 class="app-card-title">{{ $viewAll ? 'All sync queue activity' : 'Latest sync queue activity' }}</h2>
                @unless ($viewAll)
                    <a href="{{ route('sync.all', ['region_id' => $regionFilter, 'province_id' => $provinceFilter, 'municipality_id' => $municipalityFilter, 'barangay_id' => $barangayFilter]) }}" class="app-button-secondary !px-3 !py-1.5 !text-xs">View all</a>
                @endunless
                @if ($viewAll)
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-zinc-500">
                            <span>Date</span>
                            <input type="date" wire:model.live.debounce.400ms="dateFilter" class="app-input !w-auto !py-1.5">
                        </label>
                        <label class="flex items-center gap-2 text-sm text-zinc-500">
                            <span>Rows per page</span>
                            <select wire:model.live.debounce.400ms="perPage" class="app-input !w-auto !py-1.5">
                                @foreach ([10, 15, 25, 50] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        @if ($viewAll)<th class="px-4 py-3 font-medium">#</th>@endif
                        @foreach (['Model', 'Operation', 'Queued', 'Synced', 'Attempts'] as $label)
                            <th class="px-4 py-3 font-medium">{{ $label }}</th>
                        @endforeach
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentRows as $row)
                        <tr class="app-table-row">
                            @if ($viewAll)<td>{{ $recentRows->firstItem() + $loop->index }}</td>@endif
                            <td class="font-medium text-slate-950 dark:text-white">{{ class_basename($row->model_type) }}</td>
                            <td class="capitalize">{{ $row->operation }}</td>
                            <td>{{ $row->queued_at?->format('M d, Y h:i A') }}</td>
                            <td>{{ $row->synced_at?->format('M d, Y h:i A') ?? 'Pending' }}</td>
                            <td>{{ $row->attempts }}</td>
                            <td>
                                @if ($row->synced_at)
                                    <span class="status-pill status-verified">Synced</span>
                                @elseif ($row->last_error)
                                    <span class="status-pill status-rejected" title="{{ $row->last_error }}">Failed</span>
                                @else
                                    <span class="status-pill status-pending">Queued</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $viewAll ? 7 : 6 }}" class="px-4 py-8 text-center text-zinc-500">No sync queue activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($viewAll)
            <div class="p-4">{{ $recentRows->links() }}</div>
        @endif
    </section>
</div>
