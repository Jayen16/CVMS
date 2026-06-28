<div class="app-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Potential duplicate child records</h1>
            <p class="page-subtitle">Review clusters with matching name or guardian contact on the same birthdate.</p>
        </div>
    </div>

    <div class="space-y-4">
        @forelse ($groups as $group)
            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">{{ $group['reason'] }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 font-medium">Child</th>
                                <th class="px-4 py-3 font-medium">Birthdate</th>
                                <th class="px-4 py-3 font-medium">Barangay</th>
                                <th class="px-4 py-3 font-medium">Guardian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['children'] as $child)
                                <tr class="app-table-row">
                                    <td><a href="{{ route('children.show', $child) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300" wire:navigate>{{ $child->full_name }}</a></td>
                                    <td>{{ $child->birthdate->format('M d, Y') }}</td>
                                    <td>{{ $child->barangay?->name }}</td>
                                    <td>{{ $child->guardian_name }}{{ $child->guardian_contact ? ' | '.$child->guardian_contact : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <p class="text-sm text-zinc-500">No likely duplicates found.</p>
        @endforelse
    </div>
</div>
