    <div class="app-page">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Registry</p>
                <h1 class="page-title">Child profiles</h1>
                <p class="page-subtitle">Registry of children covered by the clinic barangays.</p>
            </div>
            @if (auth()->user()->canManageChildren())
                <a href="{{ route('children.create') }}" class="app-button-primary">New child</a>
            @endif
        </div>


        @if (! auth()->user()->isParent())
            <form method="GET" action="{{ route('children.index') }}" class="app-panel flex flex-col gap-3 md:flex-row md:items-end md:justify-between" x-data="{ loading: false }" @submit="loading = true">
                <label class="grid flex-1 gap-2 text-sm">
                    <span class="font-medium text-slate-800 dark:text-zinc-100">Filter by vaccination taken</span>
                    <select name="vaccine_type_id" class="app-input">
                        <option value="">All vaccinations</option>
                        @foreach ($vaccines as $vaccine)
                            <option value="{{ $vaccine->id }}" @selected((int) $selectedVaccineTypeId === $vaccine->id)>{{ $vaccine->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex gap-2">
                    <button class="app-button-primary inline-flex items-center gap-2" :disabled="loading"><span x-show="loading" x-cloak class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span><span x-text="loading ? 'Filtering…' : 'Filter'"></span></button>
                    <a href="{{ route('children.index') }}" class="app-button-secondary">Clear</a>
                </div>
            </form>
        @endif
        <div class="app-card">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">Child</th>
                        <th class="px-4 py-3 font-medium">Age</th>
                        <th class="px-4 py-3 font-medium">Barangay</th>
                        <th class="px-4 py-3 font-medium">Records</th>
                        <th class="px-4 py-3 font-medium">Completed doses</th>
                        @if (auth()->user()->canArchiveChildren())
                            <th class="px-4 py-3 font-medium">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($children as $child)
                        <tr class="app-table-row">
                            <td>
                                <a href="{{ route('children.show', $child) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300">{{ $child->full_name }}</a>
                                <div class="text-xs text-zinc-500">{{ ucfirst($child->sex) }} | Born {{ $child->birthdate->format('M d, Y') }}</div>
                            </td>
                            @if (auth()->user()->canArchiveChildren())
                                <td>
                                    <form method="POST" action="{{ route('children.archive', $child->id) }}" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Archive this child record? Clinical history will be retained.')">
                                        @csrf
                                        <select name="archive_reason" class="app-input !w-auto !py-1.5 text-xs" aria-label="Archive reason for {{ $child->full_name }}" required>
                                            <option value="">Reason…</option>
                                            <option value="Inactive">Inactive</option>
                                            <option value="Transferred">Transferred</option>
                                            <option value="Duplicate">Duplicate</option>
                                            <option value="Deceased">Deceased</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <button class="app-button-danger !px-3 !py-1.5 !text-xs">Archive</button>
                                    </form>
                                </td>
                            @endif
                            <td>{{ $child->ageLabel() }}</td>
                            <td>{{ $child->barangay->name }}</td>
                            <td>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $child->vaccinations->count() }}</span>
                            </td>
                            <td>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-900">
                                    {{ $child->completed_doses_count }} out of {{ $child->total_doses_count }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->canArchiveChildren() ? 6 : 5 }}" class="px-4 py-8 text-center text-zinc-500">No child profiles found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $children->links() }}
    </div>
