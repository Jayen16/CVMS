<div class="app-page">
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
        </div>

        @if (auth()->user()->canManageBarangayStaff() || auth()->user()->isSuperAdmin())
            <form method="POST" action="{{ route('sync.manual') }}">
                @csrf
            <button class="app-button-primary inline-flex items-center gap-2" aria-label="Sync data now">
                <flux:icon.arrow-path class="size-4" />
                <span>Sync now</span>
            </button>
            </form>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <x-stat-card label="Pending sync" :value="$pendingCount" />
        <x-stat-card label="Last processed" :value="$latestStatus?->last_processed ?? 0" />
        <x-stat-card label="Last failed" :value="$latestStatus?->last_failed ?? 0" />
    </div>

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

    <section class="app-card">
        <div class="app-card-header">
            <h2 class="app-card-title">Recent sync queue activity</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">Model</th>
                        <th class="px-4 py-3 font-medium">Operation</th>
                        <th class="px-4 py-3 font-medium">Queued</th>
                        <th class="px-4 py-3 font-medium">Synced</th>
                        <th class="px-4 py-3 font-medium">Attempts</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentRows as $row)
                        <tr class="app-table-row">
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
                        <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No sync queue activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
