<x-layouts::app :title="__('Defaulters')">
    <div class="app-page">
        <div class="page-heading">
            <div>
                <h1 class="page-title">Defaulter and recall list</h1>
                <p class="page-subtitle">Track children who are overdue for at least 7, 14, or 30 days.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('defaulters.index', ['days' => 7]) }}" class="app-button-secondary">7+ days</a>
            <a href="{{ route('defaulters.index', ['days' => 14]) }}" class="app-button-secondary">14+ days</a>
            <a href="{{ route('defaulters.index', ['days' => 30]) }}" class="app-button-secondary">30+ days</a>
        </div>

        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Overdue by {{ $threshold }} days or more</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 font-medium">Child</th>
                            <th class="px-4 py-3 font-medium">Barangay</th>
                            <th class="px-4 py-3 font-medium">Suggested vaccine</th>
                            <th class="px-4 py-3 font-medium">Guideline due</th>
                            <th class="px-4 py-3 font-medium">Days overdue</th>
                            <th class="px-4 py-3 font-medium">Guardian contacts</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($defaulters as $entry)
                            <tr class="app-table-row">
                                <td><a href="{{ route('children.show', $entry['child']) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300">{{ $entry['child']->full_name }}</a></td>
                                <td>{{ $entry['child']->barangay?->name }}</td>
                                <td>{{ $entry['suggestion']['vaccine_name'] }} dose {{ $entry['suggestion']['dose_number'] }}</td>
                                <td>{{ $entry['suggestion']['due_at']->format('M d, Y') }}</td>
                                <td>{{ $entry['days_overdue'] }}</td>
                                <td>
                                    {{ $entry['child']->guardian_contact ?? 'No guardian contact' }}
                                    @if ($entry['child']->parents->isNotEmpty())
                                        <div class="text-xs text-zinc-500">{{ $entry['child']->parents->pluck('phone')->filter()->implode(', ') }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No defaulters for this threshold.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
