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
            <form method="GET" action="{{ route('children.index') }}" class="app-panel flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
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
                    <button class="app-button-primary">Filter</button>
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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($children as $child)
                        <tr class="app-table-row">
                            <td>
                                <a href="{{ route('children.show', $child) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300">{{ $child->full_name }}</a>
                                <div class="text-xs text-zinc-500">{{ ucfirst($child->sex) }} | Born {{ $child->birthdate->format('M d, Y') }}</div>
                            </td>
                            <td>{{ $child->ageLabel() }}</td>
                            <td>{{ $child->barangay->name }}</td>
                            <td>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $child->vaccinations->count() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No child profiles found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $children->links() }}
    </div>

