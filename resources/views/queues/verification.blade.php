<x-layouts::app :title="__('Verification Queue')">
    <div class="app-page">
        <div class="page-heading">
            <div>
                <h1 class="page-title">Pending verification queue</h1>
                <p class="page-subtitle">Review parent-submitted records by barangay, vaccine, date, and source.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('verification-queue.index') }}" class="app-panel grid gap-4 md:grid-cols-5">
            @if (auth()->user()->isAdmin())
                <x-form-field label="Barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" :value="$filters['barangayId']" />
            @endif
            <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" :value="$filters['vaccineTypeId']" />
            <x-form-field label="Source" name="source" type="select" :options="['outside_clinic' => 'Outside clinic', 'barangay_clinic' => 'Barangay clinic']" :value="$filters['source']" />
            <x-form-field label="From" name="from" type="date" :value="request('from')" />
            <x-form-field label="To" name="to" type="date" :value="request('to')" />
            <div class="md:col-span-5 flex gap-2">
                <button class="app-button-primary">Apply filters</button>
                <a href="{{ route('verification-queue.index') }}" class="app-button-secondary">Reset</a>
            </div>
        </form>

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
                                <td>{{ str($record->source)->replace('_', ' ')->title() }}</td>
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
