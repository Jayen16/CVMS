<div class="app-page grid gap-6 lg:grid-cols-[1fr_380px]">
        <section class="flex flex-col gap-4">
            @if (session('status'))
                <div class="app-alert-success">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('setup_link'))
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-100">
                    <p class="font-semibold">Email delivery is set to log mode in this environment.</p>
                    <p class="mt-1 break-all">
                        Setup link:
                        <a href="{{ session('setup_link') }}" class="font-semibold underline underline-offset-2">{{ session('setup_link') }}</a>
                    </p>
                </div>
            @endif

            <div>
                <p class="eyebrow">Administration</p>
                <h1 class="page-title">{{ $managedRole === 'barangay_admin' ? 'Barangay admin accounts' : 'Nurse accounts' }}</h1>
                <p class="page-subtitle">
                    {{ $managedRole === 'barangay_admin'
                        ? 'Create barangay admins for each assigned clinic area. You can optionally give them nurse access too.'
                        : 'Invite nurses by email and assign them to the barangay where they record child vaccination data.' }}
                </p>
            </div>

            <div class="app-card overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Roles</th>
                            <th class="px-4 py-3 font-medium">Barangay</th>
                            <th class="px-4 py-3 font-medium">Setup</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($staff as $member)
                            <tr class="app-table-row">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $member->name }}</td>
                                <td>{{ $member->email }}</td>
                                <td>{{ $member->displayRole() }}</td>
                                <td>{{ $member->barangay?->name ?? 'Unassigned' }}</td>
                                <td>
                                    @if ($member->isArchived())
                                        <span class="status-pill status-rejected">Archived</span>
                                    @elseif ($member->invitation_accepted_at)
                                        <span class="status-pill status-verified">Configured</span>
                                    @else
                                        <span class="status-pill status-pending">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($member->isArchived())
                                        <span class="text-xs font-medium text-slate-500">Archived account</span>
                                    @elseif ($member->invitation_accepted_at)
                                        <span class="status-pill {{ $member->is_active ? 'status-verified' : 'status-rejected' }}">
                                            {{ $member->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    @else
                                        <span class="text-xs font-medium text-slate-500">Waiting for setup</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($member->isArchived())
                                            <form method="POST" action="{{ route('nurses.restore', $member) }}">
                                                @csrf
                                                <button class="app-button-secondary !px-3 !py-1.5 !text-xs">Restore</button>
                                            </form>
                                        @elseif ($member->invitation_accepted_at)
                                            <form method="POST" action="{{ route('nurses.toggle', $member) }}">
                                                @csrf
                                                <button class="app-button-secondary !px-3 !py-1.5 !text-xs">{{ $member->is_active ? 'Deactivate' : 'Activate' }}</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('nurses.setup-link', $member) }}">
                                                @csrf
                                                <button class="app-button-secondary !px-3 !py-1.5 !text-xs">Resend link</button>
                                            </form>
                                        @endif

                                        @unless ($member->isArchived())
                                            <form method="POST" action="{{ route('nurses.destroy', $member) }}" onsubmit="return confirm('Archive this account?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="app-button-danger !px-3 !py-1.5 !text-xs">Archive</button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $staff->links() }}
        </section>

        <form method="POST" action="{{ route('nurses.store') }}" class="app-panel grid content-start gap-4">
            @csrf
            <h2 class="app-card-title">{{ $managedRole === 'barangay_admin' ? 'Invite barangay admin' : 'Invite nurse' }}</h2>
            <p class="text-sm text-slate-600 dark:text-zinc-300">
                {{ $managedRole === 'barangay_admin'
                    ? 'The barangay admin receives an email link to set their password. You can also grant nurse access for the same account.'
                    : 'The nurse receives an email link to set their password. Until then, the account stays pending.' }}
            </p>
            <x-form-field label="Name" name="name" />
            <x-form-field label="Email" name="email" type="email" />
            @if ($managedRole === 'barangay_admin')
                <x-form-field label="Existing barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" />
                <x-form-field label="Or new barangay" name="barangay_name" />
                <label class="flex items-start gap-3 text-sm text-slate-700 dark:text-zinc-200">
                    <input type="checkbox" name="assign_nurse_role" value="1" @checked(old('assign_nurse_role')) class="mt-1">
                    <span>Also allow this barangay admin to perform nurse actions.</span>
                </label>
            @else
                <div class="rounded-lg border border-teal-100 bg-teal-50 p-3 text-sm text-teal-950 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-100">
                    Assigned barangay: <span class="font-semibold">{{ auth()->user()->barangay?->name }}</span>
                </div>
            @endif
            <button class="app-button-primary">Send password setup link</button>
        </form>
    </div>
