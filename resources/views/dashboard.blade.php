<x-layouts::app :title="__('Dashboard')">
    <div class="app-page">
        @if (session('status'))
            <div class="app-alert-success">
                {{ session('status') }}
            </div>
        @endif

        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ strtoupper($role) }}</p>
                <h1 class="page-title">Child immunization dashboard</h1>
                <p class="page-subtitle">Track child profiles, vaccination history, and pending parent-submitted records across barangays.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('children.index') }}" class="app-button-secondary">Children</a>
                @if (auth()->user()->isNurse())
                    <a href="{{ route('children.create') }}" class="app-button-primary">New child</a>
                @elseif (auth()->user()->isAdmin())
                    <a href="{{ route('nurses.index') }}" class="app-button-primary">Manage nurses</a>
                @endif
            </div>
        </div>

        @if ($role === 'admin')
            <div class="grid gap-4 md:grid-cols-4">
                <x-stat-card label="Barangays" :value="$stats['barangays']" />
                <x-stat-card label="Nurses" :value="$stats['nurses']" />
                <x-stat-card label="Children" :value="$stats['children']" />
                <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" />
            </div>

            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Barangay statistics</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 font-medium">Barangay</th>
                                <th class="px-4 py-3 font-medium">Nurses</th>
                                <th class="px-4 py-3 font-medium">Children</th>
                                <th class="px-4 py-3 font-medium">Vaccination records</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($barangays as $barangay)
                                <tr class="app-table-row">
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $barangay->name }}</td>
                                    <td>{{ $barangay->nurses_count }}</td>
                                    <td>{{ $barangay->children_count }}</td>
                                    <td>{{ $barangay->children->sum(fn ($child) => $child->vaccinations->count()) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-zinc-500">No barangays yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @elseif ($role === 'parent')
            <div class="grid gap-4 md:grid-cols-2">
                <x-stat-card label="Linked children" :value="$stats['children']" />
                <x-stat-card label="Vaccination records" :value="$stats['vaccinations']" />
            </div>

            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Linked child profiles</h2>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                    @forelse ($children as $child)
                        <a href="{{ route('children.show', $child) }}" class="flex items-center justify-between px-5 py-4 transition hover:bg-teal-50/50 dark:hover:bg-zinc-800">
                            <span class="font-medium text-slate-950 dark:text-white">{{ $child->full_name }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $child->vaccinations_count }} records</span>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-sm text-zinc-500">No linked child profiles yet.</p>
                    @endforelse
                </div>
            </section>
        @else
            <div class="grid gap-4 md:grid-cols-3">
                <x-stat-card label="Assigned barangay" :value="$stats['barangay']" />
                <x-stat-card label="Children" :value="$stats['children']" />
                <x-stat-card label="Vaccination records" :value="$stats['vaccinations']" />
            </div>

            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Recent child profiles</h2>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                    @forelse ($children as $child)
                        <a href="{{ route('children.show', $child) }}" class="flex items-center justify-between px-5 py-4 transition hover:bg-teal-50/50 dark:hover:bg-zinc-800">
                            <span class="font-medium text-slate-950 dark:text-white">{{ $child->full_name }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $child->vaccinations_count }} records</span>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-sm text-zinc-500">No child profiles yet.</p>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
</x-layouts::app>
