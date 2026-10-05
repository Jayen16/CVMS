<div class="app-page">
    <div wire:loading.flex class="fixed inset-x-0 top-0 z-[60] items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Filtering data…
    </div>
    <div class="page-heading">
        <div>
            <h1 class="page-title">{{ $view === 'history' ? 'Verification history' : 'Pending verification queue' }}</h1>
            <p class="page-subtitle">{{ $view === 'history' ? 'Review verified and rejected vaccination records.' : 'Review parent-submitted records by barangay, vaccine, date, and source.' }}</p>
        </div>
    </div>

    <div class="app-card flex flex-wrap gap-1 p-1">
        <button type="button" wire:click="setView('pending')" class="flex-1 rounded-xl px-4 py-2.5 text-sm font-semibold transition sm:flex-none" :class="$wire.view === 'pending' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-teal-50 dark:text-zinc-300 dark:hover:bg-zinc-800'">Pending review</button>
        <button type="button" wire:click="setView('history')" class="flex-1 rounded-xl px-4 py-2.5 text-sm font-semibold transition sm:flex-none" :class="$wire.view === 'history' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-teal-50 dark:text-zinc-300 dark:hover:bg-zinc-800'">Verification history</button>
    </div>

    <div class="app-panel grid gap-4 md:grid-cols-2 lg:grid-cols-6">
        <x-form-field label="Search child" name="child_search" placeholder="Child name" :value="$childSearch" wire:model.live.debounce.400ms="childSearch" />
        @if (auth()->user()->isSuperAdmin())
            <x-form-field label="Barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" :value="$barangay_id" wire:model.live.debounce.400ms="barangay_id" />
        @endif
        <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" :value="$vaccine_type_id" wire:model.live.debounce.400ms="vaccine_type_id" />
        <x-form-field label="Source" name="source" type="select" :options="['outside_clinic' => 'Outside clinic', 'barangay_clinic' => 'Barangay clinic']" :value="$source" wire:model.live.debounce.400ms="source" />
        <x-form-field label="From" name="from" type="date" :value="$from" wire:model.live.debounce.400ms="from" />
        <x-form-field label="To" name="to" type="date" :value="$to" wire:model.live.debounce.400ms="to" />
    </div>

    <section class="app-card">
        <div class="grid gap-4 p-3 sm:p-4">
            @forelse ($records as $record)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('children.show', $record->child) }}" class="break-words font-semibold text-slate-900 hover:text-teal-700 dark:text-white dark:hover:text-teal-300" wire:navigate>{{ $record->child->full_name }}</a>
                            <p class="mt-0.5 text-xs text-zinc-500">{{ $record->child->barangay?->name ?? 'No barangay' }}</p>
                        </div>
                        <span class="status-pill shrink-0 @if ($record->verification_status === 'verified') status-verified @elseif ($record->verification_status === 'rejected') status-rejected @else status-pending @endif">{{ ucfirst($record->verification_status) }}</span>
                    </div>
                    <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800"><div class="record-section-heading"><span>✓</span><strong>Information submitted</strong></div></div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                        <div><dt class="text-xs text-zinc-500">Vaccine</dt><dd class="mt-0.5 font-medium">{{ $record->vaccineType->name }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">Date given</dt><dd class="mt-0.5">{{ $record->administered_at->format('M d, Y') }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">Source</dt><dd class="mt-0.5">{{ str($record->source)->replace('_', ' ')->title() }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">Submitted by</dt><dd class="mt-0.5 break-words">{{ $record->submitter?->name ?? 'N/A' }}</dd></div>
                    </dl>
                    <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800"><div class="record-section-heading"><span>✓</span><strong>Proof attached</strong></div><div class="mt-2 text-xs">@if ($record->proofPaths() !== [])<x-proof-photo-viewer :record="$record" />@else<span class="text-zinc-500">No proof attached</span>@endif</div></div>
                    @if ($record->verification_status === 'pending')
                        <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800"><div class="record-section-heading record-section-heading-pending"><span>◷</span><strong>Waiting for approval</strong></div><p class="mt-1 pl-7 text-xs text-slate-500">Review the submitted information and proof before making a decision.</p>@if (auth()->user()->canVerifyVaccinations())<div class="mt-3 grid grid-cols-2 gap-2"><button type="button" wire:click="promptVerify('{{ $record->id }}')" class="app-button-primary w-full justify-center !px-3 !py-2 !text-xs">Verify</button><button type="button" wire:click="promptReject('{{ $record->id }}')" class="app-button-danger w-full justify-center !px-3 !py-2 !text-xs">Reject</button></div>@endif</div>
                    @else
                        <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800"><div class="record-section-heading @if ($record->verification_status === 'rejected') record-section-heading-rejected @endif"><span>@if ($record->verification_status === 'verified')✓ @else! @endif</span><strong>{{ $record->verification_status === 'verified' ? 'Verified by Nurse' : 'Rejected by Nurse' }}</strong></div><p class="mt-1 pl-7 text-xs text-slate-500">{{ $record->verifier?->name ?? $record->recordedByDisplayName() }}@if ($record->verified_at) · {{ $record->verified_at->format('M d, Y g:i A') }}@endif</p>@if ($record->nurseReviewRemarks())<p class="mt-3 pl-7 whitespace-pre-line text-sm text-slate-700 dark:text-zinc-200"><strong class="text-slate-500">Nurse remarks:</strong> {{ $record->nurseReviewRemarks() }}</p>@endif</div>
                    @endif
                </article>
            @empty
                <div class="col-span-full flex min-h-56 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-6 py-10 text-center dark:border-zinc-700 dark:bg-zinc-950/40">
                    <span class="flex size-12 items-center justify-center rounded-full bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-6 4h3m6-11v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-7 0h10"/>
                        </svg>
                    </span>
                    <p class="mt-3 font-semibold text-slate-900 dark:text-white">{{ $view === 'history' ? 'No verification history yet' : 'No pending vaccinations' }}</p>
                    <p class="mt-1 max-w-md text-sm text-slate-500 dark:text-zinc-400">{{ $view === 'history' ? 'Verified and rejected records will appear here after a nurse reviews them.' : 'New parent-submitted vaccination records will appear here when they are ready for nurse review.' }}</p>
                </div>
            @endforelse
        </div>
        <div class="hidden">
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

                <div class="mt-5">
                    <label for="review-remark" class="mb-2 block text-sm font-medium text-slate-800 dark:text-zinc-100">{{ $pendingAction === 'reject' ? 'Rejection remark' : 'Nurse review note' }} @if ($pendingAction === 'reject')<span class="text-red-600">*</span>@else<span class="text-xs font-normal text-slate-500">(optional)</span>@endif</label>
                    <textarea id="review-remark" wire:model="rejectionRemark" rows="4" maxlength="1000" class="app-input w-full" placeholder="{{ $pendingAction === 'reject' ? 'Explain why this vaccination record was rejected so the parent can correct it.' : 'Add a note about the proof or verification decision.' }}"></textarea>
                    @error('rejectionRemark')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="mt-4">
                    <label for="review-photos" class="mb-2 block text-sm font-medium text-slate-800 dark:text-zinc-100">Additional supporting photos <span class="text-xs font-normal text-slate-500">(optional)</span></label>
                    <input id="review-photos" type="file" wire:model="reviewPhotos" accept="image/*" multiple class="app-input w-full">
                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Attach up to 5 additional photos. The parent’s original proof will be preserved.</p>
                    @error('reviewPhotos')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    @error('reviewPhotos.*')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

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
