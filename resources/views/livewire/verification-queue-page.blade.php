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
                            <td>{{ str($record->source)->replace('_', ' ')->title() }}</td>
                            <td>{{ $record->submitter?->name ?? 'N/A' }}</td>
                            @if (auth()->user()->canVerifyVaccinations())
                                <td>
                                    <div class="flex gap-2">
                                        <button wire:click="verify({{ $record->id }})" class="app-button-primary !px-3 !py-1.5 !text-xs">Verify</button>
                                        <button wire:click="reject({{ $record->id }})" class="app-button-danger !px-3 !py-1.5 !text-xs">Reject</button>
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
</div>
