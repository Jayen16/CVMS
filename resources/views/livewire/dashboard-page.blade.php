<div class="app-page">
    @if (session('status'))
        <div class="app-alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ strtoupper(str_replace('_', ' ', $role)) }}</p>
            <h1 class="page-title">Child immunization dashboard</h1>
            <p class="page-subtitle">Track child profiles, vaccination history, and pending parent-submitted records across barangays.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if (auth()->user()->isNurse())
                <a href="{{ route('children.index') }}" class="app-button-secondary" wire:navigate>Children</a>
                <a href="{{ route('verification-queue.index') }}" class="app-button-secondary" wire:navigate>Verification queue</a>
            @endif
            @if (auth()->user()->isAdmin())
                <a href="{{ route('sync.index') }}" class="app-button-secondary inline-flex items-center gap-2" wire:navigate>
                    <flux:icon.arrow-path class="size-4" />
                    <span>Sync data</span>
                </a>
            @endif
            @if (auth()->user()->isNurse())
                <a href="{{ route('children.create') }}" class="app-button-primary" wire:navigate>New child</a>
            @elseif (auth()->user()->canManageBarangayStaff())
                <a href="{{ route(auth()->user()->canManageBarangayAdmins() ? 'municipal-admins.index' : 'nurses.index') }}" class="app-button-primary" wire:navigate>{{ auth()->user()->canManageBarangayAdmins() ? 'Manage barangay admins' : 'Manage nurses' }}</a>
            @endif
        </div>
    </div>

    @if ($role === 'superadmin')
        <div class="grid gap-4 md:grid-cols-6">
            <x-stat-card label="Barangays" :value="$stats['barangays']" :href="route('reports.index')" />
            <x-stat-card label="Barangay admins" :value="$stats['barangayAdmins']" :href="route('municipal-admins.index')" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" :href="route('nurses.index')" />
            <x-stat-card label="Children" :value="$stats['children']" :href="route('children.index')" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" :href="route('reports.index')" />
            @if (auth()->user()->isAdmin())
                <x-stat-card label="Pending sync" :value="$stats['pendingSync']" :href="route('sync.index')" />
            @endif
        </div>

        <div class="mt-4 max-w-sm">
            <x-stat-card label="Pending verification" :value="$stats['pending']" :href="route('verification-queue.index')" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Children by barangay" subtitle="Registered children in the top five barangays." orientation="horizontal" :data="$barangayChildrenChart" />
            <x-dashboard-bar-chart title="Vaccination records by barangay" subtitle="Total vaccination records in the top five barangays." orientation="horizontal" :data="$barangayVaccinationChart" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Pending verification by barangay" subtitle="Top five barangays by records waiting for review." orientation="horizontal" :data="$barangayPendingChart" />
            <x-dashboard-bar-chart title="Staff coverage by barangay" subtitle="Top five barangays by assigned admins and nurses." orientation="horizontal" :data="$barangayStaffChart" />
        </div>

        <section class="app-card">
            <div class="app-card-header">
                <div>
                    <h2 class="app-card-title">Barangay statistics</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Compare staffing, registered children, and vaccination activity at a glance.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 font-medium">Barangay</th>
                            <th class="px-4 py-3 font-medium">Admins</th>
                            <th class="px-4 py-3 font-medium">Nurses</th>
                            <th class="px-4 py-3 font-medium">Children</th>
                            <th class="px-4 py-3 font-medium">Vaccination records</th>
                            <th class="px-4 py-3 font-medium">Pending</th>
                            <th class="px-4 py-3 font-medium">Records / child</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($barangays as $barangay)
                            <tr class="app-table-row">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $barangay->name }}</td>
                                <td>{{ $barangay->barangay_admins_count }}</td>
                                <td>{{ $barangay->nurses_count }}</td>
                                <td>{{ $barangay->children_count }}</td>
                                <td>{{ $barangay->vaccinations_count }}</td>
                                <td>
                                    <span class="status-pill {{ $barangay->pending_vaccinations_count > 0 ? 'status-pending' : 'status-verified' }}">
                                        {{ $barangay->pending_vaccinations_count }}
                                    </span>
                                </td>
                                <td>{{ $barangay->children_count > 0 ? number_format($barangay->vaccinations_count / $barangay->children_count, 1) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-zinc-500">No barangays yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($barangays, 'links'))
                <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">{{ $barangays->links() }}</div>
            @endif
        </section>
    @elseif ($role === 'barangay_admin')
        <div class="grid gap-4 md:grid-cols-5">
            <x-stat-card label="Assigned barangay" :value="$stats['barangay']" :href="route('reports.index')" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" :href="route('nurses.index')" />
            <x-stat-card label="Children" :value="$stats['children']" :href="route('children.index')" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" :href="route('reports.index')" />
            <x-stat-card label="Pending sync" :value="$stats['pendingSync']" :href="route('sync.index')" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Vaccination activity" subtitle="Administered records over the last six months." :data="$monthlyVaccinationChart" />
            <x-dashboard-pie-chart title="Children by sex" subtitle="Sex distribution of registered children." :data="$sexChart" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Immunization progress" subtitle="Children grouped by their next recommended action." orientation="horizontal" :data="$immunizationStatusChart" />
            <x-dashboard-bar-chart title="Verification status" subtitle="Records in your barangay by review status." :data="$statusChart" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Population Coverage" subtitle="Compare the authorized target with registered children in your barangay." :data="$targetPopulationChart" />
            <x-dashboard-pie-chart title="Missed-dose risk" subtitle="Risk distribution for children in your barangay." :data="$riskChart" />
        </div>

    @elseif ($role === 'municipal_admin')
        <div class="grid gap-4 md:grid-cols-4 lg:grid-cols-7">
            <x-stat-card label="Assigned municipality" :value="$stats['municipality']" :href="route('reports.index')" />
            <x-stat-card label="Barangays" :value="$stats['barangays']" :href="route('reports.index')" />
            <x-stat-card label="Barangay admins" :value="$stats['barangayAdmins']" :href="route('municipal-admins.index')" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" :href="route('nurses.index')" />
            <x-stat-card label="Children" :value="$stats['children']" :href="route('children.index')" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" :href="route('reports.index')" />
            <x-stat-card label="Pending verification" :value="$stats['pending']" :href="route('verification-queue.index')" />
        </div>
        <section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 border-l-4 border-teal-500 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700 dark:text-teal-300">Municipality overview</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">Barangay operations</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">Review barangay performance and municipality activity from one place.</p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="{{ route('reports.index') }}" class="app-button-secondary" wire:navigate>Reports</a>
                    <a href="{{ route('audit-logs.index') }}" class="app-button-secondary" wire:navigate>Audit logs</a>
                </div>
            </div>
        </section>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Children by barangay" subtitle="Registered children across barangays in {{ $stats['municipality'] }}." orientation="horizontal" :data="$barangayChildrenChart" />
            <x-dashboard-bar-chart title="Vaccination records by barangay" subtitle="Vaccination activity across barangays in {{ $stats['municipality'] }}." orientation="horizontal" :data="$barangayVaccinationChart" />
        </div>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Barangay admins by barangay" subtitle="Assigned Barangay Admin accounts in each barangay." orientation="horizontal" :data="$barangayAdminsChart" />
            <x-dashboard-bar-chart title="Nurses by barangay" subtitle="Assigned nurses in each barangay." orientation="horizontal" :data="$barangayNursesChart" />
        </div>
        <div class="mt-4">
            <x-dashboard-bar-chart title="Insufficient vaccine inventory" subtitle="Barangays with zero or negative available stock, grouped by vaccine type." orientation="horizontal" bar-class="fill-red-500 dark:fill-red-400" suffix=" types" :data="$insufficientInventoryChart" />
        </div>
        <section class="app-card mt-4">
            <div class="app-card-header">
                <div>
                    <h2 class="app-card-title">Barangays in {{ $stats['municipality'] }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">Monitor staffing, registered children, and vaccination activity for every barangay under this municipality/city.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead><tr><th class="px-4 py-3 font-medium">Barangay</th><th class="px-4 py-3 font-medium">Admins</th><th class="px-4 py-3 font-medium">Nurses</th><th class="px-4 py-3 font-medium">Children</th><th class="px-4 py-3 font-medium">Vaccination records</th><th class="px-4 py-3 font-medium">Pending</th><th class="px-4 py-3 font-medium">Records / child</th></tr></thead>
                    <tbody>
                        @forelse ($barangays as $barangay)
                            <tr class="app-table-row">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $barangay->name }}</td>
                                <td>{{ $barangay->barangay_admins_count }}</td>
                                <td>{{ $barangay->nurses_count }}</td>
                                <td>{{ $barangay->children_count }}</td>
                                <td>{{ $barangay->vaccinations_count }}</td>
                                <td>
                                    <span class="status-pill {{ $barangay->pending_vaccinations_count > 0 ? 'status-pending' : 'status-verified' }}">
                                        {{ $barangay->pending_vaccinations_count }}
                                    </span>
                                </td>
                                <td>{{ $barangay->children_count > 0 ? number_format($barangay->vaccinations_count / $barangay->children_count, 1) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-zinc-500">No barangays assigned to this municipality.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($role === 'parent')
        <div class="grid gap-4 md:grid-cols-3">
            <x-stat-card label="Linked children" :value="$stats['children']" />
            <x-stat-card label="Vaccination records" :value="$stats['vaccinations']" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Vaccination verification" subtitle="Your vaccination records by review status." :data="$statusChart" />
        </div>

        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">This month’s family due calendar</h2>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                @forelse ($calendarItems as $date => $items)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                        <div class="text-sm font-semibold text-slate-950 dark:text-white">{{ \Illuminate\Support\Carbon::parse($date)->format('M d, Y') }}</div>
                        <div class="mt-3 space-y-3">
                            @foreach ($items as $item)
                                <a href="{{ route('children.show', $item['child']) }}" class="block rounded-lg bg-white p-3 ring-1 ring-slate-200 transition hover:bg-teal-50 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:bg-zinc-800" wire:navigate>
                                    <div class="font-medium text-slate-950 dark:text-white">{{ $item['child']->full_name }}</div>
                                    <div class="mt-1 text-sm text-slate-600 dark:text-zinc-300">
                                        {{ $item['suggestion']['vaccine_name'] }} dose {{ $item['suggestion']['dose_number'] }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">No due items in the current calendar month.</p>
                @endforelse
            </div>
        </section>

        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Linked child profiles</h2>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($children as $child)
                    <a href="{{ route('children.show', $child) }}" class="flex items-center justify-between px-5 py-4 transition hover:bg-teal-50/50 dark:hover:bg-zinc-800" wire:navigate>
                        <span class="font-medium text-slate-950 dark:text-white">{{ $child->full_name }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $child->vaccinations_count }} records</span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-sm text-zinc-500">No linked child profiles yet.</p>
                @endforelse
            </div>
        </section>
    @else
        <div class="grid gap-4 md:grid-cols-5">
            <x-stat-card label="Assigned barangay" :value="$stats['barangay']" :href="route('reports.index')" />
            <x-stat-card label="Children" :value="$stats['children']" :href="route('children.index')" />
            <x-stat-card label="Vaccination records" :value="$stats['vaccinations']" :href="route('reports.index')" />
            <x-stat-card label="Pending verification" :value="$stats['pending']" :href="route('verification-queue.index')" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Children by age" subtitle="Age distribution of children in your barangay." :data="$ageChart" />
            <x-dashboard-pie-chart title="Children by sex" subtitle="Sex distribution of children in your barangay." :data="$sexChart" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Available vaccine stock" subtitle="Available doses by vaccine type in your barangay." orientation="horizontal" :data="$stockChart" />
            <x-dashboard-bar-chart title="Vaccination activity" subtitle="Administered records over the last six months in your barangay." :data="$monthlyVaccinationChart" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dashboard-bar-chart title="Immunization progress" subtitle="Children grouped by their next recommended action." orientation="horizontal" :data="$immunizationStatusChart" />
            <x-dashboard-bar-chart title="Verification status" subtitle="Records in your barangay by review status." :data="$statusChart" />
        </div>

        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Recent child profiles</h2>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($children as $child)
                    <a href="{{ route('children.show', $child) }}" class="flex items-center justify-between px-5 py-4 transition hover:bg-teal-50/50 dark:hover:bg-zinc-800" wire:navigate>
                        <span class="font-medium text-slate-950 dark:text-white">{{ $child->full_name }}</span>
                        <span class="text-right">
                            <span class="block rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $child->vaccinations_count }} records</span>
                            <span class="mt-1 block text-xs text-slate-500 dark:text-zinc-400">Last updated {{ $child->updated_at?->format('M d, Y h:i A') ?? '—' }}</span>
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-sm text-zinc-500">No child profiles yet.</p>
                @endforelse
            </div>
        </section>
    @endif

</div>
