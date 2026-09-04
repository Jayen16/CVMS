<x-layouts::app.sidebar :title="'Locations'">
    <flux:main>
        <div class="app-page space-y-6 p-6">
            <div><p class="eyebrow">Platform administration</p><h1 class="page-title">Locations</h1><p class="page-subtitle">Manage the Region → Province → Municipality → Barangay hierarchy. User lists appear only on Municipality and Barangay rows.</p></div>
            @if (session('status'))<div class="app-alert-success">{{ session('status') }}</div>@endif
            <details class="app-card p-4"><summary class="cursor-pointer font-semibold">Add location</summary><div class="mt-4 grid gap-4 md:grid-cols-4">
                <form method="POST" action="{{ route('locations.regions.store') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">@csrf<x-form-field label="Region name" name="name" /><x-form-field label="PSGC code" name="code" /><button class="app-button-primary w-full">Add region</button></form>
                <form method="POST" action="{{ route('locations.provinces.store') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">@csrf<x-form-field label="Region" name="region_id" type="select" :options="$regions->pluck('name', 'id')" /><x-form-field label="Province name" name="name" /><x-form-field label="PSGC code" name="code" /><button class="app-button-primary w-full">Add province</button></form>
                <form method="POST" action="{{ route('locations.municipalities.store') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">@csrf<x-form-field label="Province" name="province_id" type="select" :options="$regions->flatMap->provinces->pluck('name', 'id')" /><x-form-field label="Municipality name" name="name" /><x-form-field label="PSGC code" name="code" /><button class="app-button-primary w-full">Add municipality</button></form>
                <form method="POST" action="{{ route('locations.barangays.store') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">@csrf<x-form-field label="Municipality" name="municipality_id" type="select" :options="$regions->flatMap->provinces->flatMap->municipalities->pluck('name', 'id')" /><x-form-field label="Barangay name" name="name" /><button class="app-button-primary w-full">Add barangay</button></form>
            </div></details>
            <form method="GET" class="app-card relative z-20 grid gap-4 overflow-visible p-4 md:grid-cols-4" x-data="{ loading: false }" @submit="loading = true">
                @php($provinces = $regions->flatMap->provinces->unique('id'))
                @php($municipalities = $provinces->flatMap->municipalities->unique('id'))
                @php($barangays = $municipalities->flatMap->barangays->unique('id'))
                <x-searchable-location-filter label="Region" name="region" :options="$regions->pluck('name', 'id')" :value="$filters['region'] ?? null" />
                <x-searchable-location-filter label="Province" name="province" :options="$provinces->pluck('name', 'id')" :value="$filters['province'] ?? null" />
                <x-searchable-location-filter label="Municipality" name="municipality" :options="$municipalities->pluck('name', 'id')" :value="$filters['municipality'] ?? null" />
                <x-searchable-location-filter label="Barangay" name="barangay" :options="$barangays->pluck('name', 'id')" :value="$filters['barangay'] ?? null" />
            <div class="flex gap-2 md:col-span-4"><button class="app-button-primary inline-flex items-center gap-2" :disabled="loading"><span x-show="loading" x-cloak class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span><span x-text="loading ? 'Filtering…' : 'Apply filters'"></span></button><a href="{{ route('groups.index') }}" class="app-button-secondary">Clear</a></div>
            </form>
            <section class="app-card overflow-x-auto"><div class="app-card-header"><h2 class="app-card-title">Location directory</h2></div><div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($regions as $region)
                    <details open><summary class="cursor-pointer px-5 py-4 font-semibold">{{ $region->name }}</summary><div class="border-t border-slate-200 pl-5 dark:border-zinc-800">
                        @foreach ($region->provinces as $province)
                            <details open><summary class="cursor-pointer px-5 py-3 font-medium">{{ $province->name }}</summary><div class="border-t border-slate-200 pl-5 dark:border-zinc-800">
                                @foreach ($province->municipalities as $municipality)
                                    @php($municipalityUsers = $users->where('municipality_id', $municipality->id)->whereNull('barangay_id')->filter(fn ($user) => in_array('municipal_admin', $user->rolesList(), true))->values())
                                    <details><summary class="cursor-pointer px-5 py-3 font-medium">{{ $municipality->name }} <span class="text-xs text-zinc-500">({{ $municipalityUsers->count() }} users, {{ $municipality->facilities->count() }} facilities)</span></summary><div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                                        <x-location-users-table :users="$municipalityUsers" :add-route="route('locations.municipalities.users.store', $municipality)" :location-tree="$locationTree" heading="Municipality users" :roles="['municipal_admin']" :manage-route="route('central.facilities.index', ['source' => 'groups', 'region' => $region->id, 'province' => $province->id, 'municipality' => $municipality->id])" />
                                        <div class="ml-5 border-l border-slate-200 pl-4 dark:border-zinc-800">
                                        @foreach ($municipality->barangays as $barangay)
                                            @php($barangayUsers = $users->where('barangay_id', $barangay->id)->filter(fn ($user) => in_array($user->role, ['barangay_admin', 'nurse'], true) || collect($user->rolesList())->intersect(['barangay_admin', 'nurse'])->isNotEmpty())->values())
                                            <details class="mt-2 border-t border-slate-100 pt-2 first:mt-0 dark:border-zinc-800"><summary class="cursor-pointer text-sm">{{ $barangay->name }} <span class="text-xs text-zinc-500">({{ $barangayUsers->count() }} users, {{ $barangay->facilities->count() }} facilities)</span></summary><div class="pl-4 pt-2"><x-location-users-table :users="$barangayUsers" :add-route="route('locations.barangays.users.store', $barangay)" :location-tree="$locationTree" heading="Barangay users" :roles="['barangay_admin', 'nurse']" :manage-route="route('central.facilities.index', ['source' => 'groups', 'region' => $region->id, 'province' => $province->id, 'municipality' => $municipality->id, 'barangay' => $barangay->id])" /></div></details>
                                        @endforeach
                                        </div>
                                    </div></details>
                                @endforeach
                            </div></details>
                        @endforeach
                    </div></details>
                @empty<p class="px-5 py-8 text-sm text-zinc-500">No locations found.</p>@endforelse
            </div></section>
        </div>
    </flux:main>
</x-layouts::app.sidebar>
