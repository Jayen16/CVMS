<div class="app-page">
    @if (session('status'))
        <div class="app-alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($role !== 'parent')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ strtoupper(str_replace('_', ' ', $role)) }}</p>
            <h1 class="page-title">{{ $role === 'parent' ? 'Your children’s immunization' : ($role === 'nurse' ? 'Barangay immunization workspace' : 'Child immunization dashboard') }}</h1>
            <p class="page-subtitle">{{ $role === 'parent' ? 'A clear view of upcoming doses, submitted records, and verification status.' : ($role === 'nurse' ? 'Record vaccinations, review submissions, and keep your barangay records current.' : 'Track child profiles, vaccination history, and pending parent-submitted records across barangays.') }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if (auth()->user()->isNurse())
                @if (auth()->user()->canViewChildrenRegistry())
                    <a href="{{ route('children.index') }}" class="app-button-secondary" wire:navigate>Children</a>
                @endif
                @if (auth()->user()->canViewVerificationQueue())
                    <a href="{{ route('verification-queue.index') }}" class="app-button-secondary" wire:navigate>Verification queue</a>
                @endif
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
    @endif

    @if ($role === 'superadmin')
        <div class="grid gap-4 md:grid-cols-6">
            <x-stat-card label="Barangays" :value="$stats['barangays']" :href="auth()->user()->canViewOversight() ? route('reports.index') : null" />
            <x-stat-card label="Barangay admins" :value="$stats['barangayAdmins']" :href="auth()->user()->canManageBarangayAdmins() ? route('municipal-admins.index') : null" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" :href="auth()->user()->canManageBarangayStaff() ? route('nurses.index') : null" />
            <x-stat-card label="Children" :value="$stats['children']" :href="auth()->user()->canViewChildrenRegistry() ? route('children.index') : null" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" :href="auth()->user()->canViewOversight() ? route('reports.index') : null" />
            @if (auth()->user()->isAdmin())
                <x-stat-card label="Pending sync" :value="$stats['pendingSync']" :href="auth()->user()->isAdmin() ? route('sync.index') : null" />
            @endif
        </div>

        <div class="mt-4 max-w-sm">
            <x-stat-card label="Pending verification" :value="$stats['pending']" :href="auth()->user()->canViewVerificationQueue() ? route('verification-queue.index') : null" />
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
            <x-stat-card label="Assigned barangay" :value="$stats['barangay']" :href="auth()->user()->canViewOversight() ? route('reports.index') : null" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" :href="auth()->user()->canManageBarangayStaff() ? route('nurses.index') : null" />
            <x-stat-card label="Children" :value="$stats['children']" :href="auth()->user()->canViewChildrenRegistry() ? route('children.index') : null" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" :href="auth()->user()->canViewOversight() ? route('reports.index') : null" />
            <x-stat-card label="Pending sync" :value="$stats['pendingSync']" :href="auth()->user()->isAdmin() ? route('sync.index') : null" />
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
            <x-stat-card label="Assigned municipality" :value="$stats['municipality']" :href="auth()->user()->canViewOversight() ? route('reports.index') : null" />
            <x-stat-card label="Barangays" :value="$stats['barangays']" :href="auth()->user()->canViewOversight() ? route('reports.index') : null" />
            <x-stat-card label="Barangay admins" :value="$stats['barangayAdmins']" :href="auth()->user()->canManageBarangayAdmins() ? route('municipal-admins.index') : null" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" :href="auth()->user()->canManageBarangayStaff() ? route('nurses.index') : null" />
            <x-stat-card label="Children" :value="$stats['children']" :href="auth()->user()->canViewChildrenRegistry() ? route('children.index') : null" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" :href="auth()->user()->canViewOversight() ? route('reports.index') : null" />
            <x-stat-card label="Pending verification" :value="$stats['pending']" :href="auth()->user()->canViewVerificationQueue() ? route('verification-queue.index') : null" />
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
            <x-dashboard-pie-chart title="Children by barangay" subtitle="Registered children across barangays in {{ $stats['municipality'] }}." :data="$barangayChildrenChart" />
            <x-dashboard-pie-chart title="Vaccination records by barangay" subtitle="Vaccination activity across barangays in {{ $stats['municipality'] }}." :data="$barangayVaccinationChart" />
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
        @php
            $upcomingItems = $calendarItems->flatten(1)->take(4);
            $pendingItems = $children->flatMap(fn ($child) => $child->vaccinations
                ->where('verification_status', 'pending')
                ->map(fn ($record) => ['child' => $child, 'record' => $record]))->take(4);
        @endphp

        <section class="dashboard-hero parent-dashboard-hero">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="eyebrow">Parent account</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950 dark:text-white">Good afternoon, {{ auth()->user()->name }}! 👋</h2>
                    <p class="mt-1 max-w-xl text-sm leading-6 text-slate-600 dark:text-zinc-300">Keep track of your children’s vaccination records, upcoming doses, and submitted records.</p>
                </div>
            </div>
        </section>

        <section class="dashboard-stat-grid parent-dashboard-stats">
            <x-stat-card label="Children" :value="$stats['children']" />
            <x-stat-card label="Total records" :value="$stats['vaccinations']" />
            <x-stat-card label="Verified" :value="collect($statusChart)->firstWhere('label', 'Verified')['value'] ?? 0" />
            <x-stat-card label="Pending" :value="collect($statusChart)->firstWhere('label', 'Pending')['value'] ?? 0" />
        </section>

        <section>
            <div class="dashboard-section-title"><h2>Upcoming vaccinations</h2><a href="{{ route('schedule-monitoring.index') }}" wire:navigate>See all <span aria-hidden="true">›</span></a></div>
            <div class="app-card mt-2 divide-y divide-slate-100 dark:divide-zinc-800">
                @forelse ($upcomingItems as $item)
                    <a href="{{ route('children.show', $item['child']) }}" class="flex items-center gap-3 p-4 transition hover:bg-teal-50/60 dark:hover:bg-zinc-800" wire:navigate>
                        <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 font-bold text-teal-700 dark:bg-teal-950 dark:text-teal-300">@if ($item['child']->photo_path)<img src="{{ route('children.photo', $item['child']) }}" alt="Photo of {{ $item['child']->full_name }}" class="size-full object-cover">@else{{ str($item['child']->full_name)->substr(0, 1) }}@endif</div>
                        <div class="min-w-0 flex-1"><p class="truncate font-semibold text-slate-950 dark:text-white">{{ $item['child']->full_name }}</p><p class="text-sm text-slate-500 dark:text-zinc-400">{{ $item['suggestion']['vaccine_name'] }} · Dose {{ $item['suggestion']['dose_number'] }}</p><p class="text-xs font-semibold text-rose-600">{{ $item['suggestion']['due_label'] ?? 'Due this month' }}</p></div>
                        <flux:icon.chevron-right class="size-4 shrink-0 text-slate-400" />
                    </a>
                @empty
                    <p class="p-5 text-sm text-zinc-500">No upcoming vaccinations this month.</p>
                @endforelse
            </div>
        </section>

        <section class="app-card">
            <div class="app-card-header flex items-center justify-between"><div><h2 class="app-card-title">Pending verification</h2><p class="mt-1 text-xs text-slate-500">Records submitted and waiting for review</p></div><a href="{{ route('children.index') }}" class="text-xs font-semibold text-teal-700" wire:navigate>See all ›</a></div>
            <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                @forelse ($pendingItems as $item)
                    <a href="{{ route('children.show', $item['child']) }}" class="flex items-center gap-3 p-4 transition hover:bg-amber-50/60 dark:hover:bg-zinc-800" wire:navigate><div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300"><flux:icon.clock class="size-5" /></div><div class="min-w-0 flex-1"><p class="font-semibold text-slate-950 dark:text-white">{{ $item['child']->full_name }}</p><p class="text-sm text-slate-500 dark:text-zinc-400">{{ $item['record']->vaccineType?->name ?? 'Vaccination record' }} · Dose {{ $item['record']->dose_number }}</p><p class="text-xs text-amber-600">Waiting for verification</p></div><flux:icon.chevron-right class="size-4 text-slate-400" /></a>
                @empty
                    <p class="p-5 text-sm text-zinc-500">No records are waiting for verification.</p>
                @endforelse
            </div>
        </section>

        <section>
            <div class="dashboard-section-title"><h2>Quick actions</h2></div>
            <div class="app-card mt-2 p-4"><div class="grid grid-cols-2 gap-3">
                @if (auth()->user()->canViewChildrenRegistry())
                    <a href="{{ route('children.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.users class="size-6 text-teal-600" />View children</a>
                @endif
                <a href="{{ route('notifications.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.bell class="size-6 text-amber-500" />Notifications</a>
            </div></div>
        </section>

        <section class="app-card">
            <div class="app-card-header flex items-center justify-between"><div><h2 class="app-card-title">My children</h2><p class="mt-1 text-xs text-slate-500">Vaccination progress at a glance</p></div><a href="{{ route('children.index') }}" class="text-xs font-semibold text-teal-700" wire:navigate>View all ›</a></div>
            <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                @forelse ($children as $child)
                    @php($childVerified = $child->vaccinations->where('verification_status', 'verified')->count())
                    @php($childPending = $child->vaccinations->where('verification_status', 'pending')->count())
                    <a href="{{ route('children.show', $child) }}" class="flex items-center gap-3 p-4 transition hover:bg-teal-50/60 dark:hover:bg-zinc-800" wire:navigate>
                        <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 font-bold text-teal-700 dark:bg-teal-950 dark:text-teal-300">@if ($child->photo_path)<img src="{{ route('children.photo', $child) }}" alt="Photo of {{ $child->full_name }}" class="size-full object-cover">@else{{ str($child->full_name)->substr(0, 1) }}@endif</div><div class="min-w-0 flex-1"><p class="font-semibold text-slate-950 dark:text-white">{{ $child->full_name }}</p><p class="text-sm text-slate-500 dark:text-zinc-400">{{ $child->birthdate?->age ?? '—' }} years old · {{ $child->vaccinations_count }} records</p><p class="mt-1 text-xs"><span class="text-emerald-600">● {{ $childVerified }} verified</span><span class="ml-3 text-amber-600">● {{ $childPending }} pending</span></p></div><flux:icon.chevron-right class="size-4 text-slate-400" />
                    </a>
                @empty
                    <p class="p-5 text-sm text-zinc-500">No linked child profiles yet.</p>
                @endforelse
            </div>
        </section>

        <section class="app-card">
            <div class="app-card-header flex items-center justify-between"><h2 class="app-card-title">This month’s family due calendar</h2><a href="{{ auth()->user()->isParent() ? route('family-schedule.index') : route('schedule-monitoring.index') }}" class="text-xs font-semibold text-teal-700" wire:navigate>See all ›</a></div>
            <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                @forelse ($calendarItems as $date => $items)
                    <div class="flex gap-4 p-4"><div class="w-14 shrink-0 text-center"><p class="text-xs font-semibold uppercase text-teal-700">{{ \Illuminate\Support\Carbon::parse($date)->format('M') }}</p><p class="text-2xl font-bold text-slate-950 dark:text-white">{{ \Illuminate\Support\Carbon::parse($date)->format('d') }}</p></div><div class="space-y-2">@foreach ($items as $item)<a href="{{ route('children.show', $item['child']) }}" class="block text-sm" wire:navigate><span class="font-semibold text-slate-950 dark:text-white">{{ $item['child']->full_name }}</span><span class="block text-slate-500">{{ $item['suggestion']['vaccine_name'] }} · Dose {{ $item['suggestion']['dose_number'] }}</span></a>@endforeach</div></div>
                @empty
                    <p class="p-5 text-sm text-zinc-500">No due items in the current calendar month.</p>
                @endforelse
            </div>
        </section>

        <x-dashboard-bar-chart title="Vaccination activity" subtitle="Last 6 months" :data="$monthlyVaccinationChart" />
    @else
        <section class="dashboard-hero">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="eyebrow">Nurse workspace · {{ $stats['barangay'] }}</p><h2 class="mt-1 text-xl font-bold text-slate-950 dark:text-white">Good afternoon, {{ auth()->user()->name }}! 👋</h2><p class="mt-1 max-w-xl text-sm leading-6 text-slate-600 dark:text-zinc-300">Manage child profiles, record vaccinations, and review parent submissions from your barangay.</p></div>
                <a href="{{ route('children.create') }}" class="app-button-primary shrink-0" wire:navigate><flux:icon.plus class="mr-2 size-4" />Add child</a>
            </div>
        </section>

        <section class="dashboard-stat-grid">
            <x-stat-card label="Children" :value="$stats['children']" :href="auth()->user()->canViewChildrenRegistry() ? route('children.index') : null" />
            <x-stat-card label="Vaccination records" :value="$stats['vaccinations']" :href="auth()->user()->canViewOversight() ? route('reports.index') : null" />
            <x-stat-card label="Pending verification" :value="$stats['pending']" :href="auth()->user()->canViewVerificationQueue() ? route('verification-queue.index') : null" />
            <x-stat-card label="Vaccine stock" :value="collect($stockChart)->sum('value')" :href="auth()->user()->canViewInventory() ? route('vaccine-inventory.index') : null" />
        </section>

        <section>
            <div class="dashboard-section-title"><h2>Quick actions</h2></div>
            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <a href="{{ route('children.create') }}" class="dashboard-action-tile" wire:navigate><flux:icon.user-plus class="size-6 text-teal-600" />Add child</a>
                <a href="{{ route('verification-queue.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.clipboard-document-check class="size-6 text-amber-500" />Review records</a>
                <a href="{{ route('vaccine-schedules.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.calendar-days class="size-6 text-sky-600" />View schedule</a>
                <a href="{{ route('vaccine-inventory.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.archive-box class="size-6 text-indigo-500" />Inventory</a>
            </div>
        </section>

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
            <div class="app-card-header flex items-center justify-between"><div><h2 class="app-card-title">Recent child profiles</h2><p class="mt-1 text-xs text-slate-500">Recently updated in your barangay</p></div><a href="{{ route('children.index') }}" class="text-xs font-semibold text-teal-700" wire:navigate>View all ›</a></div>
            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($children as $child)
                    <a href="{{ route('children.show', $child) }}" class="flex items-center justify-between px-5 py-4 transition hover:bg-teal-50/50 dark:hover:bg-zinc-800" wire:navigate>
                        <span class="flex items-center gap-3 font-medium text-slate-950 dark:text-white"><span class="flex size-10 items-center justify-center rounded-full bg-sky-100 font-bold text-sky-700 dark:bg-sky-950 dark:text-sky-300">{{ str($child->full_name)->substr(0, 1) }}</span>{{ $child->full_name }}</span>
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
