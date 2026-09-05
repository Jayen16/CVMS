<x-layouts::app :title="__('Population Background')">
<div class="app-page">
    <div class="page-heading">
        <div>
            <p class="eyebrow">CHILD POPULATION BACKGROUND</p>
            <h1 class="page-title">Authorized population data</h1>
            <p class="page-subtitle">Maintain the official child targets used as denominators in vaccination coverage reports.</p>
        </div>
        @if($canManage && $isManagePage)
            <a href="{{ route('population-background.index', request()->only(['region_id', 'province_id', 'municipality_id', 'barangay_id'])) }}" class="app-button-secondary">Back to population matrix</a>
        @elseif($canManage)
            <a href="{{ route('population-background.manage') }}" class="app-button-primary inline-flex items-center gap-2"><flux:icon.clipboard-document-list class="size-4" />Manage records</a>
        @endif
    </div>

    @if (session('status')) <div class="app-alert-success">{{ session('status') }}</div> @endif

    @if ($canManage && $isManagePage)
        <section class="app-card overflow-hidden" x-data="{ tab: null, region: '', province: '', municipality: '', barangay: '' }">
            <div class="grid divide-y divide-slate-200 dark:divide-zinc-800 md:grid-cols-2 md:divide-x md:divide-y-0">
                <button type="button" @click="tab = tab === 'manual' ? null : 'manual'" class="flex items-center justify-between px-5 py-4 text-left text-sm font-semibold transition hover:bg-slate-50 dark:hover:bg-zinc-900" :class="tab === 'manual' ? 'bg-teal-50/60 text-teal-800 dark:bg-teal-950/30 dark:text-teal-300' : 'text-slate-700 dark:text-zinc-200'" :aria-expanded="tab === 'manual'">
                    <span>Add manually</span><span class="text-lg" x-text="tab === 'manual' ? '−' : '+'"></span>
                </button>
                <button type="button" @click="tab = tab === 'upload' ? null : 'upload'" class="flex items-center justify-between px-5 py-4 text-left text-sm font-semibold transition hover:bg-slate-50 dark:hover:bg-zinc-900" :class="tab === 'upload' ? 'bg-teal-50/60 text-teal-800 dark:bg-teal-950/30 dark:text-teal-300' : 'text-slate-700 dark:text-zinc-200'" :aria-expanded="tab === 'upload'">
                    <span>Upload Excel / CSV</span><span class="text-lg" x-text="tab === 'upload' ? '−' : '+'"></span>
                </button>
            </div>

            <div x-show="tab === 'manual'" role="tabpanel" class="border-t border-slate-200 p-5 dark:border-zinc-800">
            <h2 class="app-card-title">Add population target</h2>
            <p class="mt-1 text-sm text-zinc-500">Enter either a municipality-wide target or a barangay target. Include the source used for authorization.</p>
            <form method="POST" action="{{ route('population-background.store') }}" class="mt-4 grid gap-4 md:grid-cols-4">
                @csrf
                @if(auth()->user()->isSuperAdmin())
                    <label class="space-y-1.5"><span class="text-sm font-medium">Region</span><select name="region_id" x-model="region" @change="province = ''; municipality = ''; barangay = ''" class="app-input"><option value="">Select region</option>@foreach($regions as $region)<option value="{{ $region->id }}">{{ $region->name }}</option>@endforeach</select></label>
                    <label class="space-y-1.5"><span class="text-sm font-medium">Province</span><select name="province_id" x-model="province" @change="municipality = ''; barangay = ''" :disabled="region === ''" class="app-input"><option value="">Select province</option>@foreach($provinces as $province)<option value="{{ $province->id }}" x-show="region !== '' && region === '{{ $province->region_id }}'">{{ $province->name }}</option>@endforeach</select></label>
                @endif
                <label class="space-y-1.5"><span class="text-sm font-medium">Municipality</span><select name="municipality_id" x-model="municipality" @change="barangay = ''" @if(auth()->user()->isSuperAdmin()) :disabled="province === ''" @endif class="app-input"><option value="">Select municipality</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @if(auth()->user()->isSuperAdmin()) x-show="province !== '' && province === '{{ $municipality->province_id }}'" @endif>{{ $municipality->name }}</option>@endforeach</select></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Barangay <span class="font-normal text-zinc-500">(optional)</span></span><select name="barangay_id" x-model="barangay" @if(auth()->user()->isSuperAdmin()) :disabled="municipality === ''" @endif class="app-input"><option value="">Municipality-wide</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @if(auth()->user()->isSuperAdmin()) x-show="municipality !== '' && municipality === '{{ $barangay->municipality_id }}'" @endif>{{ $barangay->name }} ({{ $barangay->municipalityRelation?->name }})</option>@endforeach</select></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Reference year</span><input name="reference_year" type="number" min="2000" max="2100" value="{{ old('reference_year', now()->year) }}" class="app-input" required></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Age group</span><input name="age_group" placeholder="e.g. 0–11 months" class="app-input" required></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Sex</span><select name="sex" class="app-input" required><option value="both">Both</option><option value="female">Female</option><option value="male">Male</option></select></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Target population</span><input name="target_population" type="number" min="0" class="app-input" required></label>
                <label class="space-y-1.5 md:col-span-2"><span class="text-sm font-medium">Source</span><input name="source" placeholder="e.g. Municipal Health Office 2026 masterlist" class="app-input" required></label>
                <div class="md:col-span-4"><button class="app-button-primary">Save authorized target</button></div>
            </form>
            @if ($errors->any()) <p class="mt-3 text-sm text-red-600">{{ $errors->first() }}</p> @endif
            </div>

            <div x-show="tab === 'upload'" x-cloak role="tabpanel" class="border-t border-slate-200 p-5 dark:border-zinc-800">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><h2 class="app-card-title">Upload population matrix</h2><p class="mt-1 max-w-2xl text-sm text-zinc-500">Upload an Excel workbook (.xlsx) or CSV using columns: municipality, barangay, sex, age_group, then one column per year.</p></div>
                    <a href="{{ route('population-background.template') }}" class="app-button-secondary">Download template</a>
                </div>
                <form method="POST" action="{{ route('population-background.upload') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="min-w-72 flex-1 space-y-1.5"><span class="text-sm font-medium">Excel or CSV file</span><input type="file" name="file" accept=".xlsx,.csv,.txt" class="app-input" required></label>
                    <label class="min-w-72 flex-1 space-y-1.5"><span class="text-sm font-medium">Source</span><input name="source" placeholder="e.g. 2026 CPH projection" class="app-input" required></label>
                    <button class="app-button-primary">Import matrix</button>
                </form>
            </div>
        </section>
    @endif

    @if (! $isManagePage)
    <section class="app-card p-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            @if(auth()->user()->isSuperAdmin())
                <label class="min-w-52 flex-1 space-y-1.5"><span class="text-sm font-medium">Region</span><select name="region_id" class="app-input"><option value="">All regions</option>@foreach($regions as $region)<option value="{{ $region->id }}" @selected($selectedRegion === (string) $region->id)>{{ $region->name }}</option>@endforeach</select></label>
                <label class="min-w-52 flex-1 space-y-1.5"><span class="text-sm font-medium">Province</span><select name="province_id" class="app-input"><option value="">All provinces</option>@foreach($provinces as $province)<option value="{{ $province->id }}" @selected($selectedProvince === (string) $province->id)>{{ $province->name }}</option>@endforeach</select></label>
            @endif
            <label class="min-w-64 flex-1 space-y-1.5"><span class="text-sm font-medium">Municipality</span><select name="municipality_id" class="app-input"><option value="">All municipalities</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected($selectedMunicipality === (string) $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></label>
            <label class="min-w-64 flex-1 space-y-1.5"><span class="text-sm font-medium">Barangay</span><select name="barangay_id" class="app-input"><option value="">All barangays</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected($selectedBarangay === (string) $barangay->id)>{{ $barangay->name }} ({{ $barangay->municipalityRelation?->name }})</option>@endforeach</select></label>
            <button class="app-button-secondary inline-flex items-center gap-2"><flux:icon.map-pin class="size-4" />Apply location</button>
            @if($selectedMunicipality !== '' || $selectedBarangay !== '' || $selectedRegion !== '' || $selectedProvince !== '')<a href="{{ route('population-background.index') }}" class="app-button-secondary inline-flex items-center gap-2"><flux:icon.arrow-path class="size-4" />All locations</a>@endif
        </form>
        <p class="mt-3 text-xs text-zinc-500">When All locations is selected, targets are summed by sex, age group, and reference year.</p>
    </section>

    @if ($requiresLocationSelection)
    <section class="app-card p-8 text-center">
        <h2 class="app-card-title">Select a region to view population background</h2>
        <p class="mt-2 text-sm text-zinc-500">Choose a location above to load authorized population targets.</p>
    </section>
    @else
    <section class="app-card overflow-hidden">
        <div class="app-card-header"><div><h2 class="app-card-title">Authorized population matrix</h2><p class="text-sm text-zinc-500">Targets are grouped by location, sex, and age group. Only the latest applicable reference year is used in coverage calculations.</p></div></div>
        @forelse($matrix->groupBy('location') as $location => $locationRows)
            <div class="border-b border-slate-200 last:border-b-0 dark:border-zinc-800">
                <div class="population-matrix-title">{{ $location }}</div>
                <div class="overflow-x-auto"><table class="population-matrix w-full min-w-[640px] table-fixed"><thead><tr><th class="w-40">Sex</th><th class="w-48">Age group</th>@foreach($years as $year)<th class="min-w-32 text-right">{{ $year }}</th>@endforeach</tr></thead><tbody>
                    @foreach($locationRows->groupBy('sex') as $sex => $sexRows)
                        @foreach($sexRows->values() as $rowIndex => $row)
                            <tr class="app-table-row">
                                @if($rowIndex === 0)<td rowspan="{{ $sexRows->count() }}" class="bg-slate-100 align-top font-semibold text-slate-800 dark:bg-zinc-800 dark:text-zinc-100">{{ $sex }}</td>@endif
                                <td class="font-medium">{{ $row['age_group'] }}</td>
                                @foreach($years as $year)<td class="text-right tabular-nums">{{ isset($row['values'][$year]) ? number_format($row['values'][$year]) : '—' }}</td>@endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody></table></div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-zinc-500">No authorized population targets yet.</div>
        @endforelse
    </section>
    @endif
    @endif

    @if ($canManage && $isManagePage)
        <section class="app-card overflow-hidden" x-data="{ editing: null }">
            <div class="app-card-header"><h2 class="app-card-title">Manage population records</h2><p class="text-sm text-zinc-500">Edit or remove manually added and uploaded population targets.</p></div>
            <div class="flex justify-end border-b border-slate-200 px-5 py-3 dark:border-zinc-800">
                <form method="GET" action="{{ route('population-background.manage') }}" class="flex items-center gap-2 text-sm">
                    <label for="population-per-page" class="text-zinc-500">Show</label>
                    <select id="population-per-page" name="per_page" class="app-input min-h-9 w-auto py-1.5" onchange="this.form.submit()">
                        @foreach([10, 25, 50, 100] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>@endforeach
                    </select>
                    <span class="text-zinc-500">per page</span>
                </form>
            </div>
            <div class="overflow-x-auto"><table class="app-table w-full min-w-[1000px]"><thead><tr><th>#</th><th>Location</th><th>Year</th><th>Sex</th><th>Age group</th><th>Target</th><th>Source</th><th></th></tr></thead><tbody>
                    @forelse($manageRecords as $record)
                        <tr x-show="editing !== '{{ $record->id }}'" class="app-table-row"><td class="text-zinc-500">{{ $manageRecords->firstItem() + $loop->index }}</td><td>{{ $record->locationLabel() }}</td><td>{{ $record->reference_year }}</td><td>{{ $record->sex }}</td><td>{{ $record->age_group }}</td><td>{{ number_format($record->target_population) }}</td><td>{{ $record->source }}</td><td class="whitespace-nowrap"><button type="button" @click="editing = '{{ $record->id }}'" class="text-sm font-medium text-teal-600 hover:text-teal-800">Edit</button><form method="POST" action="{{ route('population-background.destroy', $record) }}" class="ml-3 inline" onsubmit="return confirm('Remove this population record?')">@csrf @method('DELETE')<button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400">Remove</button></form></td></tr>
                        <tr x-show="editing === '{{ $record->id }}'" x-cloak><td colspan="8" class="bg-slate-50 p-5 dark:bg-zinc-900"><form method="POST" action="{{ route('population-background.update', $record) }}" class="grid gap-4 md:grid-cols-4">@csrf @method('PUT')<label class="space-y-1.5"><span class="text-sm font-medium">Municipality</span><select name="municipality_id" class="app-input"><option value="">Select municipality</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected($record->municipality_id === $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></label><label class="space-y-1.5"><span class="text-sm font-medium">Barangay</span><select name="barangay_id" class="app-input"><option value="">Municipality-wide</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected($record->barangay_id === $barangay->id)>{{ $barangay->name }} ({{ $barangay->municipalityRelation?->name }})</option>@endforeach</select></label><label class="space-y-1.5"><span class="text-sm font-medium">Reference year</span><input name="reference_year" type="number" value="{{ $record->reference_year }}" class="app-input" required></label><label class="space-y-1.5"><span class="text-sm font-medium">Age group</span><input name="age_group" value="{{ $record->age_group }}" class="app-input" required></label><label class="space-y-1.5"><span class="text-sm font-medium">Sex</span><input name="sex" value="{{ $record->sex }}" class="app-input" required></label><label class="space-y-1.5"><span class="text-sm font-medium">Target population</span><input name="target_population" type="number" min="0" value="{{ $record->target_population }}" class="app-input" required></label><label class="space-y-1.5 md:col-span-2"><span class="text-sm font-medium">Source</span><input name="source" value="{{ $record->source }}" class="app-input" required></label><div class="flex items-end gap-2 md:col-span-4"><button class="app-button-primary">Save changes</button><button type="button" @click="editing = null" class="app-button-secondary">Cancel</button></div></form></td></tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-6 text-center text-zinc-500">No records in this location.</td></tr>
                    @endforelse
                </tbody></table></div>
            <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">{{ $manageRecords->links() }}</div>
        </section>
    @endif
</div>
</x-layouts::app>
