<div class="app-page">
    <div wire:loading.flex class="fixed inset-x-0 top-0 z-[60] items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Filtering data…
    </div>
    <div class="page-heading">
        <div>
            <h1 class="page-title">Pending verification queue</h1>
            <p class="page-subtitle">Review parent-submitted records by barangay, vaccine, date, and source.</p>
        </div>
    </div>

    <div class="app-panel grid gap-4 md:grid-cols-5">
        @if (auth()->user()->isSuperAdmin())
            <x-form-field label="Barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" :value="$barangay_id" wire:model.live.debounce.400ms="barangay_id" />
        @endif
        <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" :value="$vaccine_type_id" wire:model.live.debounce.400ms="vaccine_type_id" />
        <x-form-field label="Source" name="source" type="select" :options="['outside_clinic' => 'Outside clinic', 'barangay_clinic' => 'Barangay clinic']" :value="$source" wire:model.live.debounce.400ms="source" />
        <x-form-field label="From" name="from" type="date" :value="$from" wire:model.live.debounce.400ms="from" />
        <x-form-field label="To" name="to" type="date" :value="$to" wire:model.live.debounce.400ms="to" />
    </div>

    <section class="app-card">
        <div class="grid gap-3 p-3 lg:hidden">
            @forelse ($records as $record)
                <article class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('children.show', $record->child) }}" class="break-words font-semibold text-slate-900 hover:text-teal-700 dark:text-white dark:hover:text-teal-300" wire:navigate>{{ $record->child->full_name }}</a>
                            <p class="mt-0.5 text-xs text-zinc-500">{{ $record->child->barangay?->name ?? 'No barangay' }}</p>
                        </div>
                        <span class="shrink-0 text-sm text-zinc-500">{{ $record->administered_at->format('M d, Y') }}</span>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-slate-100 pt-3 text-sm dark:border-zinc-800">
                        <div><dt class="text-xs text-zinc-500">Vaccine</dt><dd class="mt-0.5 font-medium">{{ $record->vaccineType->name }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">Source</dt><dd class="mt-0.5">{{ str($record->source)->replace('_', ' ')->title() }}</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-zinc-500">Submitted by</dt><dd class="mt-0.5 break-words">{{ $record->submitter?->name ?? 'N/A' }}</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-zinc-500">Proof</dt><dd class="mt-0.5">@if ($record->proofPaths() !== [])<x-proof-photo-viewer :record="$record" />@else<span class="text-zinc-500">No proof attached</span>@endif</dd></div>
                    </dl>
                    @if (auth()->user()->canVerifyVaccinations())
                        <div class="mt-4 flex gap-2 border-t border-slate-100 pt-3 dark:border-zinc-800">
                            <button type="button" wire:click="promptVerify('{{ $record->id }}')" class="app-button-primary flex-1 justify-center !px-3 !py-2 !text-xs">Verify</button>
                            <button type="button" wire:click="promptReject('{{ $record->id }}')" class="app-button-danger flex-1 justify-center !px-3 !py-2 !text-xs">Reject</button>
                        </div>
                    @endif
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
                        @if (auth()->user()->canVerifyVaccinations())
                            <th class="px-4 py-3 font-medium">Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="app-table-row">
                            <td><a href="{{ route('children.show', $record->child) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300" wire:navigate>{{ $record->child->full_name }}</a></td>
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
                            @if (auth()->user()->canVerifyVaccinations())
                                <td>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            wire:click="promptVerify('{{ $record->id }}')"
                                            class="app-button-primary !px-3 !py-1.5 !text-xs"
                                        >
                                            Verify
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="promptReject('{{ $record->id }}')"
                                            class="app-button-danger !px-3 !py-1.5 !text-xs"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->canVerifyVaccinations() ? 7 : 6 }}" class="px-4 py-8 text-center text-zinc-500">No pending records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $records->links() }}</div>
    </section>

    @if ($confirmingAction && $pendingRecordSummary)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">
                    {{ $pendingAction === 'verify' ? 'Verify vaccination history' : 'Reject vaccination history' }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">
                    {{ $pendingAction === 'verify'
                        ? 'Please review this submission before final verification.'
                        : 'Please confirm that this submission should be rejected.' }}
                </p>

                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/70">
                    <dl class="grid gap-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-zinc-400">Child</dt>
                            <dd class="text-right font-semibold text-slate-950 dark:text-white">{{ $pendingRecordSummary['child_name'] }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-zinc-400">Barangay</dt>
                            <dd class="text-right text-slate-950 dark:text-white">{{ $pendingRecordSummary['barangay_name'] }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-zinc-400">Vaccine</dt>
                            <dd class="text-right text-slate-950 dark:text-white">{{ $pendingRecordSummary['vaccine_name'] }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-zinc-400">Date given</dt>
                            <dd class="text-right text-slate-950 dark:text-white">{{ $pendingRecordSummary['date_given'] }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-zinc-400">Source</dt>
                            <dd class="text-right text-slate-950 dark:text-white">{{ $pendingRecordSummary['source'] }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-zinc-400">Submitted by</dt>
                            <dd class="text-right text-slate-950 dark:text-white">{{ $pendingRecordSummary['submitted_by'] }}</dd>
                        </div>
                    </dl>
                </div>

                @if ($pendingAction === 'reject')
                    <div class="mt-5">
                        <label for="rejection-remark" class="mb-2 block text-sm font-medium text-slate-800 dark:text-zinc-100">Rejection remark <span class="text-red-600">*</span></label>
                        <textarea id="rejection-remark" wire:model="rejectionRemark" rows="4" maxlength="1000" class="app-input w-full" placeholder="Explain why this vaccination record was rejected so the parent can correct it."></textarea>
                        @error('rejectionRemark')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="app-button-secondary" wire:click="cancelConfirmation">Cancel</button>
                    <button
                        type="button"
                        class="{{ $pendingAction === 'verify' ? 'app-button-primary' : 'app-button-danger' }}"
                        wire:click="confirmPendingAction"
                    >
                        {{ $pendingAction === 'verify' ? 'Final verify' : 'Confirm reject' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
