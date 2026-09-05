<div class="app-page">
    <div class="page-heading flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="eyebrow">ROUTINE IMMUNIZATION</p>
            <h1 class="page-title">Reminder History</h1>
            <p class="page-subtitle">Review vaccination reminders sent to parents and investigate failed deliveries.</p>
        </div>
        <a href="{{ route('schedule-monitoring.index', ['regionId' => $regionId, 'provinceId' => $provinceId, 'municipalityId' => $municipalityId, 'barangayId' => $barangayId]) }}" class="app-button-secondary">Back to schedule monitoring</a>
    </div>

    <section class="app-card mb-6 p-4">
        <div class="grid gap-3 md:grid-cols-[1fr_150px_150px_150px_150px_auto] md:items-end">
            <label class="space-y-1.5"><span class="text-sm font-medium">Search</span><input wire:model.live.debounce.300ms="search" class="app-input w-full" placeholder="Child, parent, vaccine, recipient" /></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Status</span><select wire:model.live="status" class="app-input w-full"><option value="all">All statuses</option><option value="sent">Sent</option><option value="pending">Pending</option><option value="failed">Failed</option></select></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Channel</span><select wire:model.live="channel" class="app-input w-full"><option value="all">All channels</option><option value="sms">SMS</option><option value="email">Email</option></select></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Created from</span><input wire:model.live="from" type="date" class="app-input w-full" /></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Created to</span><input wire:model.live="to" type="date" class="app-input w-full" /></label>
            <button type="button" wire:click="$set('search', '')" class="app-button-secondary">Clear</button>
        </div>
    </section>

    <section class="app-card overflow-hidden">
        <div class="app-card-header flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="app-card-title">Delivery records</h2><p class="mt-1 text-sm text-zinc-500">{{ $reminders->total() }} matching reminder{{ $reminders->total() === 1 ? '' : 's' }}</p></div>
            <label class="flex items-center gap-2 text-sm font-medium">Rows per page <select wire:model.live="perPage" class="app-input !w-auto"><option value="10">10</option><option value="15">15</option><option value="25">25</option><option value="50">50</option></select></label>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table w-full min-w-[1100px]">
                <thead><tr><th>Child / barangay</th><th>Parent</th><th>Vaccination schedule</th><th>Channel</th><th>Recipient</th><th>Status</th><th>Sent at</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse($reminders as $reminder)
                        @php($statusClasses = ['sent' => 'status-verified', 'failed' => 'status-rejected', 'pending' => 'bg-yellow-100 text-yellow-800'])
                        <tr class="app-table-row align-top">
                            <td><a href="{{ route('children.show', $reminder->child) }}" class="font-semibold text-teal-700 hover:underline">{{ $reminder->child->full_name }}</a><div class="text-xs text-zinc-500">{{ $reminder->child->barangay?->name ?? 'No barangay' }}</div></td>
                            <td>{{ $reminder->parent?->name ?? 'N/A' }}</td>
                            <td><span class="font-medium">{{ $reminder->vaccine_name }}</span><div class="text-xs text-zinc-500">Dose {{ $reminder->dose_number ?? '—' }} · due {{ $reminder->due_at?->format('M d, Y') }}</div></td>
                            <td>{{ strtoupper($reminder->channel) }}</td>
                            <td>{{ $reminder->recipient }}</td>
                            <td><span class="status-pill {{ $statusClasses[$reminder->status] ?? 'bg-zinc-100 text-zinc-600' }}">{{ str($reminder->status)->headline() }}</span></td>
                            <td>{{ $reminder->sent_at?->format('M d, Y g:i A') ?? '—' }}</td>
                            <td class="max-w-72 text-xs text-zinc-500">{{ $reminder->error_message ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="!px-4 !py-10 text-center text-zinc-500">No reminder history matches the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reminders->hasPages())<div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">{{ $reminders->links() }}</div>@endif
    </section>
</div>
