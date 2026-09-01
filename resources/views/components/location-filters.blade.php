@props([
    'regions' => collect(),
    'provinces' => collect(),
    'municipalities' => collect(),
    'barangays' => collect(),
    'mode' => 'query',
    'regionValue' => 'all',
    'provinceValue' => 'all',
    'municipalityValue' => 'all',
    'barangayValue' => 'all',
    'regionModel' => 'regionId',
    'provinceModel' => 'provinceId',
    'municipalityModel' => 'municipalityId',
    'barangayModel' => 'barangayId',
    'regionName' => 'region_id',
    'provinceName' => 'province_id',
    'municipalityName' => 'municipality_id',
    'barangayName' => 'barangay_id',
])

@php
    $user = auth()->user();
    $isSuperAdmin = $user?->isSuperAdmin() ?? false;
    $isMunicipalAdmin = $user?->isMunicipalAdmin() ?? false;
    $isLocationFilterVisible = $isSuperAdmin || $isMunicipalAdmin;
    $isLivewire = $mode === 'wire';
    $regionValue = filled($regionValue) ? $regionValue : 'all';
    $provinceValue = filled($provinceValue) ? $provinceValue : 'all';
    $municipalityValue = filled($municipalityValue) ? $municipalityValue : 'all';
    $barangayValue = filled($barangayValue) ? $barangayValue : 'all';
    $selectClass = 'w-full min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white';
@endphp

@if ($isLocationFilterVisible)
    <div class="app-card w-full p-4">
        <div class="border-t border-slate-200 pt-4 dark:border-zinc-700">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-teal-600 dark:text-teal-400">Location filters</p>
        <div
            class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
            @if (! $isLivewire) x-data="{ submitTimer: null, filtering: false, scheduleSubmit() { this.filtering = true; clearTimeout(this.submitTimer); this.submitTimer = setTimeout(() => this.$el.closest('form')?.requestSubmit(), 400); } }" @change="if ($event.target.name === '{{ $regionName }}') { $event.target.form.elements['{{ $provinceName }}'].value = 'all'; $event.target.form.elements['{{ $municipalityName }}'].value = 'all'; $event.target.form.elements['{{ $barangayName }}'].value = 'all'; scheduleSubmit(); } else if ($event.target.name === '{{ $provinceName }}') { $event.target.form.elements['{{ $municipalityName }}'].value = 'all'; $event.target.form.elements['{{ $barangayName }}'].value = 'all'; scheduleSubmit(); } else if ($event.target.name === '{{ $municipalityName }}') { $event.target.form.elements['{{ $barangayName }}'].value = 'all'; scheduleSubmit(); }" @endif
        >
        @if (! $isLivewire)
            <div x-show="filtering" x-cloak class="col-span-full flex items-center gap-2 text-xs text-teal-700 dark:text-teal-300" role="status" aria-live="polite">
                <span class="size-3.5 animate-spin rounded-full border-2 border-teal-200 border-t-teal-700"></span>
                <span>Filtering data…</span>
            </div>
        @endif
        @if ($isSuperAdmin)
            <label class="space-y-1.5">
                <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">Region</span>
                <select @if ($isLivewire) wire:model.live.debounce.400ms="{{ $regionModel }}" @else name="{{ $regionName }}" @endif class="{{ $selectClass }}">
                    <option value="all" @selected((string) $regionValue === 'all')>All regions</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected((string) $regionValue === (string) $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1.5">
                <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">Province</span>
                <select @if ($isLivewire) wire:model.live.debounce.400ms="{{ $provinceModel }}" @else name="{{ $provinceName }}" @endif @disabled($isSuperAdmin && (string) $regionValue === 'all') class="{{ $selectClass }}">
                    @if ($isSuperAdmin && (string) $regionValue === 'all')
                        <option value="all">Select a region first</option>
                    @else
                        <option value="all" @selected((string) $provinceValue === 'all')>All provinces</option>
                    @endif
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected((string) $provinceValue === (string) $province->id)>{{ $province->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1.5">
                <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">Municipality</span>
                <select @if ($isLivewire) wire:model.live.debounce.400ms="{{ $municipalityModel }}" @else name="{{ $municipalityName }}" @endif @disabled($isSuperAdmin && (string) $provinceValue === 'all') class="{{ $selectClass }}">
                    @if ($isSuperAdmin && (string) $provinceValue === 'all')
                        <option value="all">Select a province first</option>
                    @else
                        <option value="all" @selected((string) $municipalityValue === 'all')>All municipalities</option>
                    @endif
                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality->id }}" @selected((string) $municipalityValue === (string) $municipality->id)>{{ $municipality->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <label class="space-y-1.5">
            <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">Barangay</span>
            <select @if ($isLivewire) wire:model.live.debounce.400ms="{{ $barangayModel }}" @else name="{{ $barangayName }}" @endif @disabled($isSuperAdmin && (string) $municipalityValue === 'all') class="{{ $selectClass }}">
                @if ($isSuperAdmin && (string) $municipalityValue === 'all')
                    <option value="all">Select a municipality first</option>
                @elseif ($isSuperAdmin || $barangays->count() > 1)
                    <option value="all" @selected((string) $barangayValue === 'all')>All barangays</option>
                @endif
                @foreach ($barangays as $barangay)
                    <option value="{{ $barangay->id }}" @selected((string) $barangayValue === (string) $barangay->id)>{{ $barangay->name }}</option>
                @endforeach
            </select>
        </label>
        </div>
        </div>
    </div>
@endif
