<div class="app-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Pending verification queue</h1>
            <p class="page-subtitle">Review parent-submitted records by barangay, vaccine, date, and source.</p>
        </div>
    </div>

    <div class="app-panel grid gap-4 md:grid-cols-5">
        @if (auth()->user()->isSuperAdmin())
            <x-form-field label="Barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" :value="$barangay_id" wire:model.live="barangay_id" />
        @endif
        <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" :value="$vaccine_type_id" wire:model.live="vaccine_type_id" />
        <x-form-field label="Source" name="source" type="select" :options="['outside_clinic' => 'Outside clinic', 'barangay_clinic' => 'Barangay clinic']" :value="$source" wire:model.live="source" />
        <x-form-field label="From" name="from" type="date" :value="$from" wire:model.live="from" />
        <x-form-field label="To" name="to" type="date" :value="$to" wire:model.live="to" />
    </div>

    <section class="app-card">
        <div class="overflow-x-auto">
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
                                @foreach ($record->proofPaths() as $proofPath)
                                    <div class="text-xs">
                                        <a
                                            href="{{ route('vaccinations.proofs.show', ['record' => $record, 'proofIndex' => $loop->iteration]) }}"
                                            target="_blank"
                                            class="text-teal-700 hover:underline dark:text-teal-300"
                                        >
                                            View proof photo {{ $loop->iteration }}
                                        </a>
                                    </div>
                                @endforeach
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
