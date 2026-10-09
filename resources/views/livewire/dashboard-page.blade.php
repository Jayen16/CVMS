<div class="app-page">
    @if (session('status'))
        <div class="app-alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($role !== 'parent' && $role !== 'nurse')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ strtoupper(str_replace('_', ' ', $role)) }}</p>
            <h1 class="page-title">{{ $role === 'parent' ? 'Your children’s immunization' : ($role === 'nurse' ? 'Barangay immunization workspace' : 'Child immunization dashboard') }}</h1>
            <p class="page-subtitle">{{ $role === 'parent' ? 'A clear view of upcoming doses, submitted records, and verification status.' : ($role === 'nurse' ? 'Record vaccinations, review submissions, and keep your barangay records current.' : 'Track child profiles, vaccination history, and pending parent-submitted records across barangays.') }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('sync.index') }}" class="app-button-secondary inline-flex items-center gap-2" wire:navigate>
                    <flux:icon.arrow-path class="size-4" />
                    <span>Sync data</span>
                </a>
            @endif
            @if (auth()->user()->canManageBarangayStaff())
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
            $verifiedCount = $children->sum(fn ($child) => $child->vaccinations->where('verification_status', 'verified')->count());
            $pendingCount = $children->sum(fn ($child) => $child->vaccinations->where('verification_status', 'pending')->count());
            $overdueCount = $children->filter(fn ($child) => ($suggestion = app(\App\Services\ImmunizationSuggestionService::class)->suggestNextDose($child)) && ($suggestion['status'] ?? null) === 'overdue')->count();
            $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
        @endphp
        <div class="parent-dashboard" x-data="{ summary: null, close() { this.summary = null } }" @keydown.escape.window="close()">
            <section class="parent-welcome-panel">
                <div class="flex min-w-0 items-center gap-4">
                    <a href="{{ route('profile.edit') }}" wire:navigate class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-teal-400/60 bg-teal-100 text-2xl font-bold text-teal-700 shadow-sm dark:bg-teal-950 dark:text-teal-300" title="Update profile photo">
                        @if (auth()->user()->photo_path)
                            <img src="{{ route('profile.photo') }}" alt="Profile photo of {{ auth()->user()->name }}" class="size-full object-cover">
                        @else
                            {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                        @endif
                    </a>
                    <div class="min-w-0">
                    <p class="eyebrow">ImmuniCare · Parent dashboard</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $greeting }}, {{ auth()->user()->name }}! <span aria-hidden="true">👋</span></h1>
                    <p class="mt-1 max-w-xl text-sm leading-6 text-slate-600 dark:text-zinc-300">Here’s an overview of your children’s immunization status.</p>
                    <a href="{{ route('profile.edit') }}" wire:navigate class="mt-2 inline-block text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300">Update profile photo</a>
                    </div>
                </div>
                <div class="parent-welcome-art" aria-hidden="true"><span>✦</span><span>✚</span><span>♥</span></div>
            </section>

            <section class="parent-summary-grid" aria-label="Immunization summaries">
                @foreach ([['children', 'Children', $stats['children']], ['records', 'Vaccination records', $stats['vaccinations']], ['verified', 'Verified', $verifiedCount], ['pending', 'Awaiting verification', $pendingCount], ['overdue', 'Overdue', $overdueCount]] as [$key, $label, $value])
                    <button type="button" @click="summary = '{{ $key }}'" class="app-card block w-full cursor-pointer p-5 text-left transition hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 dark:hover:border-teal-700 dark:focus:ring-offset-zinc-950" aria-haspopup="dialog">
                        <span class="flex items-start justify-between gap-4"><span><span class="block text-sm font-medium text-slate-500 dark:text-zinc-400">{{ $label }}</span><span class="mt-2 block text-2xl font-semibold text-slate-950 dark:text-white">{{ $value }}</span></span><span class="stat-card-icon flex size-10 items-center justify-center rounded-xl bg-teal-50 text-teal-700 ring-1 ring-teal-100 dark:bg-teal-950 dark:text-teal-300 dark:ring-teal-900">@if ($key === 'children')<flux:icon.users class="size-5" />@elseif ($key === 'records')<flux:icon.beaker class="size-5" />@elseif ($key === 'verified')<flux:icon.check-circle class="size-5" />@elseif ($key === 'pending')<flux:icon.clock class="size-5" />@else<flux:icon.chart-bar class="size-5" />@endif</span></span>
                    </button>
                @endforeach
            </section>

            <div x-cloak x-show="summary" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @click.self="close()" role="presentation">
                <section x-show="summary" x-transition class="max-h-[85vh] w-full max-w-2xl overflow-hidden rounded-[1.1rem] bg-white shadow-[0_24px_70px_rgba(15,23,42,0.22)] dark:bg-zinc-900" role="dialog" aria-modal="true" aria-labelledby="parent-summary-title" @click.stop>
                    <header class="flex items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-zinc-800"><div><h2 id="parent-summary-title" class="text-base font-bold text-slate-950 dark:text-white" x-text="({children:'Your children',records:'Vaccination records',verified:'Verified records',pending:'Awaiting verification',overdue:'Overdue vaccinations'})[summary]"></h2><p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">A summary across all your linked children.</p></div><button type="button" @click="close()" class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-600 dark:hover:bg-zinc-800" aria-label="Close summary"><flux:icon.x-mark class="size-5" /></button></header>
                    <div class="max-h-[65vh] overflow-y-auto p-5">
                        <div x-show="summary === 'children'" class="space-y-3">
                            @forelse ($children as $child)
                                <a href="{{ route('children.show', $child) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 transition hover:border-teal-200 hover:bg-slate-50/60 dark:border-zinc-800 dark:hover:bg-zinc-800/50"><span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-50 text-sm font-semibold text-teal-700 ring-1 ring-teal-100 dark:bg-teal-950 dark:text-teal-300 dark:ring-teal-900">@if ($child->photo_path)<img src="{{ route('children.photo', $child) }}" alt="Photo of {{ $child->full_name }}" class="size-full object-cover">@else{{ str($child->full_name)->substr(0, 1)->upper() }}@endif</span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $child->full_name }}</span><span class="mt-1 block text-xs text-slate-500 dark:text-zinc-400">{{ $child->vaccinations_count }} vaccination records</span></span><flux:icon.chevron-right class="size-4 shrink-0 text-slate-400" /></a>
                            @empty <p class="text-sm text-slate-500">No linked child profiles.</p> @endforelse
                        </div>
                        @foreach (['records' => null, 'verified' => 'verified', 'pending' => 'pending'] as $summaryKey => $statusFilter)
                            <div x-show="summary === '{{ $summaryKey }}'" class="space-y-3">
                                @php($records = $children->flatMap(fn ($child) => $child->vaccinations->map(fn ($record) => ['child' => $child, 'record' => $record]))->when($statusFilter, fn ($items) => $items->filter(fn ($item) => $item['record']->verification_status === $statusFilter))->sortByDesc(fn ($item) => $item['record']->administered_at)->values())
                                @forelse ($records as $item)
                                    <a href="{{ route('children.show', $item['child']) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 transition hover:border-teal-200 hover:bg-slate-50/60 dark:border-zinc-800 dark:hover:bg-zinc-800/50"><span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-50 text-sm font-semibold text-teal-700 dark:bg-teal-950 dark:text-teal-300">@if ($item['child']->photo_path)<img src="{{ route('children.photo', $item['child']) }}" alt="" class="size-full object-cover">@else{{ str($item['child']->full_name)->substr(0, 1)->upper() }}@endif</span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $item['record']->vaccineType?->name ?? 'Vaccination' }}{{ $item['record']->dose_number ? ' · Dose '.$item['record']->dose_number : '' }}</span><span class="mt-1 block text-xs text-slate-500 dark:text-zinc-400">{{ $item['child']->full_name }} · {{ $item['record']->administered_at?->format('M j, Y') ?? 'Date not recorded' }}</span></span><span class="status-pill {{ $item['record']->verification_status === 'verified' ? 'status-verified' : ($item['record']->verification_status === 'pending' ? 'status-pending' : 'status-rejected') }}">{{ str($item['record']->verification_status)->headline() }}</span></a>
                                @empty <p class="text-sm text-slate-500">No matching vaccination records.</p> @endforelse
                            </div>
                        @endforeach
                        <div x-show="summary === 'overdue'" class="space-y-3">
                            @php($overdueChildren = $children->filter(fn ($child) => ($nextDoseItems->get($child->id)['suggestion']['status'] ?? null) === 'overdue'))
                            @forelse ($overdueChildren as $child)
                                @php($suggestion = $nextDoseItems->get($child->id)['suggestion'])
                                <a href="{{ route('children.show', $child) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-rose-100 p-3 transition hover:border-rose-300 dark:border-rose-900/50"><span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-rose-50 text-sm font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-300">@if ($child->photo_path)<img src="{{ route('children.photo', $child) }}" alt="" class="size-full object-cover">@else{{ str($child->full_name)->substr(0, 1)->upper() }}@endif</span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $child->full_name }}</span><span class="mt-1 block text-xs text-slate-500 dark:text-zinc-400">{{ $suggestion['vaccine_name'] ?? 'Scheduled dose' }}{{ !empty($suggestion['dose_number']) ? ' · Dose '.$suggestion['dose_number'] : '' }}{{ !empty($suggestion['due_label']) ? ' · '.$suggestion['due_label'] : '' }}</span></span><flux:icon.chevron-right class="size-4 shrink-0 text-slate-400" /></a>
                            @empty <p class="text-sm text-slate-500">No overdue vaccinations across your children.</p> @endforelse
                        </div>
                    </div>
                </section>
            </div>


            <section>
                <div class="dashboard-section-title"><h2>My children</h2><a href="{{ route('children.index') }}" wire:navigate>View all <span aria-hidden="true">›</span></a></div>
                <div class="parent-children-grid mt-2">
                    @forelse ($children as $child)
                        @php($childVerified = $child->vaccinations->where('verification_status', 'verified')->count())
                        @php($childPending = $child->vaccinations->where('verification_status', 'pending')->count())
                        @php($nextItem = $nextDoseItems->get($child->id))
                        <article class="parent-child-card"><div class="flex items-start justify-between gap-3"><a href="{{ route('children.show', $child) }}" class="parent-child-avatar" wire:navigate>@if ($child->photo_path)<img src="{{ route('children.photo', $child) }}" alt="Photo of {{ $child->full_name }}" class="size-full object-cover">@else{{ str($child->full_name)->substr(0, 1) }}@endif</a><span class="status-pill {{ $childPending ? 'status-pending' : 'status-verified' }}">{{ $childPending ? 'Action needed' : 'On schedule' }}</span></div><h3 class="mt-3"><a href="{{ route('children.show', $child) }}" class="font-bold text-slate-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300" wire:navigate>{{ $child->full_name }}</a></h3><p class="text-xs text-slate-500">{{ $child->birthdate?->age ?? '—' }} years old · {{ ucfirst($child->sex ?? '—') }}</p><div class="mt-3 flex gap-3 text-[11px]"><span class="text-emerald-600">● {{ $childVerified }} verified</span><span class="text-amber-600">● {{ $childPending }} pending</span></div><div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800"><p class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Next vaccination</p><p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $nextItem['suggestion']['vaccine_name'] ?? 'No upcoming dose' }}</p>@if ($nextItem && $nextItem['suggestion']['vaccine_type_id'])<a href="{{ route('vaccine-information.show', $nextItem['suggestion']['vaccine_type_id']) }}" class="mt-2 inline-block text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300" wire:navigate>Learn about this vaccine →</a>@endif<div class="mt-3 flex gap-2"><a href="{{ route('children.show', $child) }}" class="app-button-primary flex-1 px-2 py-2 text-center text-xs" wire:navigate>View record</a><a href="{{ route('family-schedule.index') }}" class="app-button-secondary flex-1 px-2 py-2 text-center text-xs" wire:navigate>View schedule</a></div></div></article>
                    @empty
                        <p class="app-card p-5 text-sm text-zinc-500">No linked child profiles yet.</p>
                    @endforelse
                    <section class="parent-action-card">
                    <div class="parent-card-heading"><div><span class="parent-icon parent-icon-warning"><flux:icon.exclamation-circle class="size-5" /></span><div><h2>Action needed</h2><p>Stay on top of your children’s upcoming doses.</p></div></div><span class="parent-count-badge">{{ $upcomingItems->count() }}</span></div>
                    <div class="mt-3 divide-y divide-amber-100/80 dark:divide-amber-900/40">
                    @forelse ($upcomingItems as $item)
                    <a href="{{ route('children.show', $item['child']) }}" class="parent-list-row" wire:navigate><span class="parent-list-icon"><flux:icon.calendar-days class="size-4" /></span><span class="min-w-0 flex-1"><span class="block truncate font-semibold text-slate-900 dark:text-white">{{ $item['suggestion']['vaccine_name'] }} · Dose {{ $item['suggestion']['dose_number'] }}</span><span class="block text-xs text-slate-500">{{ $item['child']->full_name }} · {{ $item['suggestion']['due_label'] ?? 'Due this month' }}</span></span><flux:icon.chevron-right class="size-4 text-slate-400" /></a>
                    @empty
                    <p class="py-5 text-sm text-slate-500">No upcoming vaccinations this month.</p>
                    @endforelse
                    </div>
                    <a href="{{ route('family-schedule.index') }}" class="app-button-primary mt-3 w-full" wire:navigate>View schedule</a>
                    </section>
                </div>
            </section>

            <div class="parent-lower-grid">
                <section class="app-card"><div class="app-card-header flex items-center justify-between"><div><h2 class="app-card-title">This month’s schedule</h2><p class="mt-1 text-xs text-slate-500">Upcoming doses for your family</p></div><a href="{{ route('family-schedule.index') }}" class="text-xs font-semibold text-teal-700" wire:navigate>View calendar ›</a></div><div class="divide-y divide-slate-100 dark:divide-zinc-800">@forelse ($calendarItems as $date => $items)<div class="flex gap-4 p-4"><div class="w-12 shrink-0 text-center"><p class="text-xs font-semibold uppercase text-teal-700">{{ \Illuminate\Support\Carbon::parse($date)->format('M') }}</p><p class="text-2xl font-bold text-slate-950 dark:text-white">{{ \Illuminate\Support\Carbon::parse($date)->format('d') }}</p></div><div class="space-y-2">@foreach ($items as $item)<a href="{{ route('children.show', $item['child']) }}" class="block text-sm" wire:navigate><span class="font-semibold text-slate-950 dark:text-white">{{ $item['child']->full_name }}</span><span class="block text-slate-500">{{ $item['suggestion']['vaccine_name'] }} · Dose {{ $item['suggestion']['dose_number'] }}</span></a>@endforeach</div></div>@empty<p class="p-5 text-sm text-zinc-500">No due items in the current calendar month.</p>@endforelse</div></section>
                <section class="app-card"><div class="app-card-header"><h2 class="app-card-title">Notifications</h2><p class="mt-1 text-xs text-slate-500">Stay updated on your records</p></div><div class="divide-y divide-slate-100 dark:divide-zinc-800"><a href="{{ route('notifications.index') }}" class="parent-notification-row" wire:navigate><span class="parent-icon parent-icon-warning"><flux:icon.bell class="size-4" /></span><span><strong>Upcoming vaccination reminder</strong><small>Check your family schedule for the next dose.</small></span></a><a href="{{ route('notifications.index') }}" class="parent-notification-row" wire:navigate><span class="parent-icon parent-icon-success"><flux:icon.check-circle class="size-4" /></span><span><strong>Record verification updates</strong><small>Review the latest status of your submissions.</small></span></a><a href="{{ route('notifications.index') }}" class="app-button-secondary m-4 block text-center text-xs" wire:navigate>View all notifications</a></div></section>
            </div>

            <section><div class="dashboard-section-title"><h2>Quick actions</h2></div><div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3">@if (auth()->user()->canViewChildrenRegistry())<a href="{{ route('children.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.users class="size-6 text-teal-600" />View children</a>@endif<a href="{{ route('notifications.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.bell class="size-6 text-amber-500" />Notifications</a><a href="{{ route('family-schedule.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.calendar-days class="size-6 text-sky-600" />Schedule</a></div></section>
            <div class="w-full min-w-0 lg:max-w-2xl">
                <x-dashboard-bar-chart title="Vaccination activity" subtitle="Last 6 months" :data="$monthlyVaccinationChart" />
            </div>
        </div>
    @else
        <div class="nurse-dashboard">
        <section class="nurse-welcome-panel">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <a href="{{ route('profile.edit') }}" wire:navigate class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-teal-400/60 bg-teal-100 text-2xl font-bold text-teal-700 shadow-sm dark:bg-teal-950 dark:text-teal-300" title="Update profile photo">
                    @if (auth()->user()->photo_path)
                        <img src="{{ route('profile.photo') }}" alt="Profile photo of {{ auth()->user()->name }}" class="size-full object-cover">
                    @else
                        {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                    @endif
                </a>
                <div class="min-w-0">
                    <p class="eyebrow">Nurse workspace · {{ $stats['barangay'] }}</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950 dark:text-white">Hello, {{ auth()->user()->name }}! 👋</h2>
                    <p class="mt-1 max-w-xl text-sm leading-6 text-slate-600 dark:text-zinc-300">Manage child profiles, record vaccinations, and review parent submissions from your barangay.</p>
                    <a href="{{ route('profile.edit') }}" wire:navigate class="mt-2 inline-block text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300">Update profile photo</a>
                </div>
            </div>
                @if (auth()->user()->canViewChildrenRegistry())
                    <a href="{{ route('children.create') }}" class="app-button-primary shrink-0" wire:navigate><flux:icon.plus class="mr-2 size-4" />Add child</a>
                @endif
            </div>
        </section>

        <section class="nurse-summary-grid">
            <x-stat-card label="Children" :value="$stats['children']" :href="auth()->user()->canViewChildrenRegistry() ? route('children.index') : null" />
            <x-stat-card label="Vaccination records" :value="$stats['vaccinations']" :href="auth()->user()->canViewOversight() ? route('reports.index') : null" />
            <x-stat-card label="Pending verification" :value="$stats['pending']" :href="auth()->user()->canViewVerificationQueue() ? route('verification-queue.index') : null" />
            <x-stat-card label="Vaccine stock" :value="collect($stockChart)->sum('value')" :href="auth()->user()->canViewInventory() ? route('vaccine-inventory.index') : null" />
        </section>

        <section class="nurse-quick-actions">
            <div class="dashboard-section-title"><h2>Quick actions</h2></div>
            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @if (auth()->user()->canViewChildrenRegistry())
                    <a href="{{ route('children.create') }}" class="dashboard-action-tile" wire:navigate><flux:icon.user-plus class="size-6 text-teal-600" />Add child</a>
                @endif
                @if (auth()->user()->canViewVerificationQueue())
                    <a href="{{ route('verification-queue.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.clipboard-document-check class="size-6 text-amber-500" />Verify vaccination</a>
                @endif
                @if (auth()->user()->canViewInventory())
                    <a href="{{ route('vaccine-inventory.index') }}" class="dashboard-action-tile" wire:navigate><flux:icon.archive-box class="size-6 text-indigo-500" />Inventory</a>
                @endif
            </div>
        </section>

        @if (auth()->user()->canViewVerificationQueue())
            <section class="nurse-action-card">
                <div class="parent-card-heading"><div><span class="parent-icon parent-icon-warning"><flux:icon.exclamation-circle class="size-5" /></span><div><h2>Action needed</h2><p>Parent-submitted records waiting for verification.</p></div></div><span class="parent-count-badge">{{ $stats['pending'] }}</span></div>
                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($pendingRecords as $record)
                        <a href="{{ route('verification-queue.index') }}" class="nurse-pending-item" wire:navigate><span class="parent-list-icon"><flux:icon.clipboard-document-check class="size-4" /></span><span class="min-w-0 flex-1"><span class="block truncate font-semibold text-slate-900 dark:text-white">{{ $record->child?->full_name ?? 'Child record' }}</span><span class="block truncate text-xs text-slate-500">{{ $record->vaccineType?->name ?? 'Vaccination record' }} · Dose {{ $record->dose_number }}</span></span><flux:icon.chevron-right class="size-4 text-slate-400" /></a>
                    @empty
                        <p class="text-sm text-slate-500">No records are waiting for verification.</p>
                    @endforelse
                </div>
                <a href="{{ route('verification-queue.index') }}" class="app-button-primary mt-4" wire:navigate>Review verification queue</a>
            </section>
        @endif

        <div
            x-data="{
                current: 0,
                count: 6,
                next() { this.current = (this.current + 1) % this.count },
                previous() { this.current = (this.current - 1 + this.count) % this.count },
            }"
            class="nurse-chart-carousel nurse-insights"
        >
            <div class="nurse-chart-carousel__track">
                <div class="nurse-chart-carousel__slide" :class="{ 'is-active': current === 0 }">
                    <x-dashboard-bar-chart title="Children by age" subtitle="Age distribution of children in your barangay." :data="$ageChart" />
                </div>
                <div class="nurse-chart-carousel__slide" :class="{ 'is-active': current === 1 }">
                    <x-dashboard-pie-chart title="Children by sex" subtitle="Sex distribution of children in your barangay." :data="$sexChart" />
                </div>
                <div class="nurse-chart-carousel__slide" :class="{ 'is-active': current === 2 }">
                    <x-dashboard-bar-chart title="Available vaccine stock" subtitle="Available doses by vaccine type in your barangay." orientation="horizontal" :data="$stockChart" />
                </div>
                <div class="nurse-chart-carousel__slide" :class="{ 'is-active': current === 3 }">
                    <x-dashboard-bar-chart title="Vaccination activity" subtitle="Administered records over the last six months in your barangay." :data="$monthlyVaccinationChart" />
                </div>
                <div class="nurse-chart-carousel__slide" :class="{ 'is-active': current === 4 }">
                    <x-dashboard-bar-chart title="Immunization progress" subtitle="Children grouped by their next recommended action." orientation="horizontal" :data="$immunizationStatusChart" />
                </div>
                <div class="nurse-chart-carousel__slide" :class="{ 'is-active': current === 5 }">
                    <x-dashboard-bar-chart title="Verification status" subtitle="Records in your barangay by review status." :data="$statusChart" />
                </div>
            </div>

            <div class="nurse-chart-carousel__controls lg:hidden">
                <button type="button" class="app-button-secondary" x-on:click="previous" aria-label="Show previous chart">
                    <span aria-hidden="true">←</span>
                    <span>Previous</span>
                </button>
                <span class="text-xs font-medium text-slate-500 dark:text-zinc-400" aria-live="polite">
                    Chart <span x-text="current + 1"></span> of <span x-text="count"></span>
                </span>
                <button type="button" class="app-button-secondary" x-on:click="next" aria-label="Show next chart">
                    <span>Next</span>
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </div>

        <section class="app-card nurse-recent-children">
            <div class="app-card-header flex items-center justify-between"><div><h2 class="app-card-title">Recent child profiles</h2><p class="mt-1 text-xs text-slate-500">Recently updated in your barangay</p></div>@if (auth()->user()->canViewChildrenRegistry())<a href="{{ route('children.index') }}" class="text-xs font-semibold text-teal-700" wire:navigate>View all ›</a>@endif</div>
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
        </div>
    @endif

</div>
