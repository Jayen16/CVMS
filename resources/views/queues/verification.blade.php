<x-layouts::app :title="__('Verification Queue')">
    <div class="app-page">
        <div class="page-heading">
            <div>
                <h1 class="page-title">Pending verification queue</h1>
                <p class="page-subtitle">Review parent-submitted records by barangay, vaccine, date, and source.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('verification-queue.index') }}" class="app-panel grid gap-4 lg:grid-cols-5" x-data="{ loading: false }" @submit="loading = true">
            @if (auth()->user()->isAdmin())
                <x-form-field label="Barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" :value="$filters['barangayId']" />
            @endif
            <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" :value="$filters['vaccineTypeId']" />
            <x-form-field label="Source" name="source" type="select" :options="['outside_clinic' => 'Outside clinic', 'barangay_clinic' => 'Barangay clinic']" :value="$filters['source']" />
            <x-form-field label="From" name="from" type="date" :value="request('from')" />
            <x-form-field label="To" name="to" type="date" :value="request('to')" />
            <div class="lg:col-span-5 flex gap-2">
                <button class="app-button-primary inline-flex items-center gap-2" :disabled="loading"><span x-show="loading" x-cloak class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span><span x-text="loading ? 'Filtering…' : 'Apply filters'"></span></button>
                <a href="{{ route('verification-queue.index') }}" class="app-button-secondary">Reset</a>
            </div>
        </form>

        <section class="app-card">
            <div class="grid gap-3 p-3 lg:hidden">
                @forelse ($records as $record)
                    <article class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('children.show', $record->child) }}" class="break-words font-semibold text-slate-900 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">{{ $record->child->full_name }}</a>
                                <p class="mt-0.5 text-xs text-zinc-500">{{ $record->child->barangay?->name ?? 'No barangay' }}</p>
                            </div>
                            <span class="shrink-0 text-sm text-zinc-500">{{ $record->administered_at->format('M d, Y') }}</span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-slate-100 pt-3 text-sm dark:border-zinc-800">
                            <div><dt class="text-xs text-zinc-500">Vaccine</dt><dd class="mt-0.5 font-medium">{{ $record->vaccineType->name }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">Source</dt><dd class="mt-0.5">{{ str($record->source)->replace('_', ' ')->title() }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">Submitted by</dt><dd class="mt-0.5 break-words">{{ $record->submitter?->name ?? 'N/A' }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">Status</dt><dd class="mt-0.5"><span class="status-pill status-pending">Pending</span></dd></div>
                            <div class="col-span-2"><dt class="text-xs text-zinc-500">Proof</dt><dd class="mt-0.5 text-sm">@if ($record->proofPaths() !== [])<x-proof-photo-viewer :record="$record" />@else<span class="text-zinc-500">No proof attached</span>@endif</dd></div>
                        </dl>
                        <div class="mt-4 flex gap-2 border-t border-slate-100 pt-3 dark:border-zinc-800">
                            <form method="POST" action="{{ route('vaccinations.verify', $record) }}" class="flex-1">@csrf<button class="app-button-primary w-full !px-3 !py-2 !text-xs">Verify</button></form>
                            <form method="POST" action="{{ route('vaccinations.reject', $record) }}" class="flex-1">@csrf<button class="app-button-danger w-full !px-3 !py-2 !text-xs">Reject</button></form>
                        </div>
                    </article>
                @empty
                    <div class="app-card p-6 text-center text-sm text-zinc-500">No pending records found.</div>
                @endforelse
            </div>
            <div class="hidden overflow-x-auto lg:block">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 font-medium">Child</th>
                            <th class="px-4 py-3 font-medium">Barangay</th>
                            <th class="px-4 py-3 font-medium">Vaccine</th>
                            <th class="px-4 py-3 font-medium">Date given</th>
                            <th class="px-4 py-3 font-medium">Source</th>
                            <th class="px-4 py-3 font-medium">Submitted by</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr class="app-table-row">
                                <td><a href="{{ route('children.show', $record->child) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300">{{ $record->child->full_name }}</a></td>
                                <td>{{ $record->child->barangay?->name }}</td>
                                <td>{{ $record->vaccineType->name }}</td>
                                <td>{{ $record->administered_at->format('M d, Y') }}</td>
                                <td>
                                    {{ str($record->source)->replace('_', ' ')->title() }}
                                    @if ($record->proofPaths() !== [])
                                        <div class="text-xs">
                                            <x-proof-photo-viewer :record="$record" />
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $record->submitter?->name ?? 'N/A' }}</td>
                                <td>
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('vaccinations.verify', $record) }}">
                                            @csrf
                                            <button class="app-button-primary !px-3 !py-1.5 !text-xs">Verify</button>
                                        </form>
                                        <form method="POST" action="{{ route('vaccinations.reject', $record) }}">
                                            @csrf
                                            <button class="app-button-danger !px-3 !py-1.5 !text-xs">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No pending records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $records->links() }}</div>
        </section>
    </div>
</x-layouts::app>
