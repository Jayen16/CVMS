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
            @if (auth()->user()->canViewChildrenRegistry())
                <a href="{{ route('children.index') }}" class="app-button-secondary" wire:navigate>Children</a>
            @endif
            @if (auth()->user()->canManageChildren())
                <a href="{{ route('children.create') }}" class="app-button-primary" wire:navigate>New child</a>
            @elseif (auth()->user()->canManageBarangayStaff())
                <a href="{{ route('nurses.index') }}" class="app-button-primary" wire:navigate>{{ auth()->user()->canManageBarangayAdmins() ? 'Manage barangay admins' : 'Manage nurses' }}</a>
            @endif
        </div>
    </div>

    @if ($role === 'superadmin')
        <div class="grid gap-4 md:grid-cols-6">
            <x-stat-card label="Barangays" :value="$stats['barangays']" />
            <x-stat-card label="Barangay admins" :value="$stats['barangayAdmins']" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" />
            <x-stat-card label="Children" :value="$stats['children']" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" />
            <x-stat-card label="Pending sync" :value="$stats['pendingSync']" />
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <x-stat-card label="Pending verification" :value="$stats['pending']" />
            <div class="app-card flex items-center justify-between">
                <div>
                    <h2 class="app-card-title">Quick actions</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">Review system-wide coverage and manage barangay admins.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('nurses.index') }}" class="app-button-secondary" wire:navigate>Barangay admins</a>
                    <a href="{{ route('reports.index') }}" class="app-button-secondary" wire:navigate>Reports</a>
                </div>
            </div>
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
                            <th class="px-4 py-3 font-medium">Admins</th>
                            <th class="px-4 py-3 font-medium">Nurses</th>
                            <th class="px-4 py-3 font-medium">Children</th>
                            <th class="px-4 py-3 font-medium">Vaccination records</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($barangays as $barangay)
                            <tr class="app-table-row">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $barangay->name }}</td>
                                <td>{{ $barangay->barangay_admins_count }}</td>
                                <td>{{ $barangay->nurses_count }}</td>
                                <td>{{ $barangay->children_count }}</td>
                                <td>{{ $barangay->children->sum(fn ($child) => $child->vaccinations->count()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-zinc-500">No barangays yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($role === 'barangay_admin')
        <div class="grid gap-4 md:grid-cols-5">
            <x-stat-card label="Assigned barangay" :value="$stats['barangay']" />
            <x-stat-card label="Nurses" :value="$stats['nurses']" />
            <x-stat-card label="Children" :value="$stats['children']" />
            <x-stat-card label="Vaccinations" :value="$stats['vaccinations']" />
            <x-stat-card label="Pending sync" :value="$stats['pendingSync']" />
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <x-stat-card label="Pending verification" :value="$stats['pending']" />
            <div class="app-card flex items-center justify-between">
                <div>
                    <h2 class="app-card-title">Quick actions</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">Monitor coverage in your barangay and manage your nurses.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('nurses.index') }}" class="app-button-secondary" wire:navigate>Nurses</a>
                    <a href="{{ route('reports.index') }}" class="app-button-secondary" wire:navigate>Reports</a>
                </div>
            </div>
        </div>

        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Recent child profiles</h2>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($children as $child)
                    <a href="{{ route('children.show', $child) }}" class="flex items-center justify-between px-5 py-4 transition hover:bg-teal-50/50 dark:hover:bg-zinc-800" wire:navigate>
                        <span class="font-medium text-slate-950 dark:text-white">{{ $child->full_name }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $child->vaccinations_count }} records</span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-sm text-zinc-500">No child profiles yet.</p>
                @endforelse
            </div>
        </section>
    @elseif ($role === 'parent')
        <div class="grid gap-4 md:grid-cols-3">
            <x-stat-card label="Linked children" :value="$stats['children']" />
            <x-stat-card label="Vaccination records" :value="$stats['vaccinations']" />
            <x-stat-card label="Pending sync" :value="$stats['pendingSync']" />
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
            <x-stat-card label="Assigned barangay" :value="$stats['barangay']" />
            <x-stat-card label="Children" :value="$stats['children']" />
            <x-stat-card label="Vaccination records" :value="$stats['vaccinations']" />
            <x-stat-card label="Pending verification" :value="$stats['pending']" />
            <x-stat-card label="Pending sync" :value="$stats['pendingSync']" />
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('verification-queue.index') }}" class="app-button-secondary" wire:navigate>Verification queue</a>
            <a href="{{ route('defaulters.index') }}" class="app-button-secondary" wire:navigate>Defaulters</a>
            <a href="{{ route('aefi-reports.index') }}" class="app-button-secondary" wire:navigate>AEFI reports</a>
        </div>

        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Recent child profiles</h2>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($children as $child)
                    <a href="{{ route('children.show', $child) }}" class="flex items-center justify-between px-5 py-4 transition hover:bg-teal-50/50 dark:hover:bg-zinc-800" wire:navigate>
                        <span class="font-medium text-slate-950 dark:text-white">{{ $child->full_name }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $child->vaccinations_count }} records</span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-sm text-zinc-500">No child profiles yet.</p>
                @endforelse
            </div>
        </section>
    @endif

    <section class="app-card">
        <div class="app-card-header">
            <h2 class="app-card-title">Clinic announcements</h2>
            @if (auth()->user()->canManageAnnouncements())
                <a href="{{ route('announcements.index') }}" class="app-button-secondary" wire:navigate>Manage announcements</a>
            @endif
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            @forelse ($announcements as $announcement)
                <article class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-semibold text-slate-950 dark:text-white">{{ $announcement->title }}</div>
                        <span class="status-pill bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200">{{ ucfirst($announcement->category) }}</span>
                    </div>
                    <div class="mt-2 text-sm text-slate-600 dark:text-zinc-300">
                        {{ $announcement->starts_on->format('M d, Y') }}@if ($announcement->ends_on) to {{ $announcement->ends_on->format('M d, Y') }}@endif
                        @if ($announcement->barangay)
                            | {{ $announcement->barangay->name }}
                        @endif
                        @if ($announcement->location)
                            | {{ $announcement->location }}
                        @endif
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $announcement->message }}</p>
                </article>
            @empty
                <p class="text-sm text-zinc-500">No active clinic announcements.</p>
            @endforelse
        </div>
    </section>
</div>
