<div class="app-page" x-data="{ inviteOpen: false, archiveOpen: false, archiveAction: '', archiveName: '' }">
        @if (auth()->user()->isSuperAdmin() && request()->routeIs('municipal-admins.*'))
            <section class="app-card lg:col-span-2">
                <p class="eyebrow">Platform administration</p>
                <h1 class="page-title">Municipal admin accounts</h1>
                <p class="page-subtitle">Assign barangay admins to barangays in your municipality. Nurses can be added by their Barangay Admin.</p>
                <div class="mt-5 overflow-x-auto">
                    <table class="app-table">
                        <thead><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Municipality</th><th class="px-4 py-3">Status</th></tr></thead>
                        <tbody>
                            @forelse ($municipalAdmins as $member)
                                <tr class="app-table-row"><td class="font-medium">{{ $member->name }}</td><td>{{ $member->email }}</td><td>{{ $member->municipality?->name ?? 'Unassigned' }}</td><td>{{ $member->is_active ? 'Active' : 'Inactive' }}</td></tr>
                            @empty <tr><td colspan="4" class="px-4 py-6 text-center text-zinc-500">No municipal admin accounts yet.</td></tr> @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            @if (auth()->user()->isSuperAdmin())
            <form method="POST" action="{{ route('municipal-admins.store') }}" class="app-panel lg:col-start-2 lg:row-start-2">
                @csrf
                <h2 class="app-card-title">Create municipal admin</h2>
                <div class="mt-4 grid gap-4"><x-form-field label="Name" name="name" /><x-form-field label="Email" name="email" type="email" /><x-form-field label="Municipality" name="municipality_id" type="select" :options="$municipalities->pluck('name', 'id')" /><button class="app-button-primary">Send password setup link</button></div>
            </form>
            @endif
        @endif
        @if (! auth()->user()->isSuperAdmin() || ! request()->routeIs('municipal-admins.*'))
        <section class="flex flex-col gap-4">
            @if (session('status'))
                <div class="app-alert-success">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('setup_link'))
                <div x-data="{ copied: false, link: @js(session('setup_link')), fallbackCopy() { const input = document.createElement('textarea'); input.value = this.link; input.setAttribute('readonly', ''); input.style.position = 'fixed'; input.style.opacity = '0'; document.body.appendChild(input); input.select(); const copied = document.execCommand('copy'); input.remove(); if (! copied) throw new Error('Clipboard fallback failed'); }, async copyLink() { try { if (navigator.clipboard?.writeText) { try { await navigator.clipboard.writeText(this.link); } catch (error) { this.fallbackCopy(); } } else { this.fallbackCopy(); } this.copied = true; setTimeout(() => this.copied = false, 1500); } catch (error) { console.warn('Could not copy setup link', error); } } }" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-100">
                    @if (config('system.instance_type') === 'facility' && config('offline.enabled'))
                        <p class="font-semibold">Offline facility setup link</p>
                        <p class="mt-1">Copy this link and open it on the nurse’s computer to set the password.</p>
                    @else
                        <p class="font-semibold">Email delivery is set to log mode in this environment.</p>
                    @endif
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <a href="{{ session('setup_link') }}" class="break-all font-semibold underline underline-offset-2">{{ session('setup_link') }}</a>
                        <button type="button" class="app-button-secondary !px-3 !py-1.5 !text-xs" @click="copyLink()" x-text="copied ? 'Copied' : 'Copy link'">Copy link</button>
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">Administration</p>
                    <h1 class="page-title">{{ $managedRole === 'barangay_admin' ? 'Barangay admin accounts' : 'Nurse accounts' }}</h1>
                    <p class="page-subtitle">
                        {{ $managedRole === 'barangay_admin'
                            ? 'Create barangay admins for barangays in your municipality. They are responsible for adding Nurses.'
                            : 'Invite nurses by email and assign them to the barangay where they record child vaccination data.' }}
                    </p>
                </div>
                <button type="button" class="app-button-primary" @click="inviteOpen = true">{{ $managedRole === 'barangay_admin' ? 'Invite Barangay Admin' : 'Invite nurse' }}</button>
            </div>

            <div class="app-card !overflow-visible">
                <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Roles</th>
                            <th class="px-4 py-3 font-medium">Barangay</th>
                            <th class="px-4 py-3 font-medium">Setup</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Access</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    @forelse ($staff as $member)
                        <tbody x-data="{ accessOpen: false, actionsOpen: false, actionsStyle: '' }">
                            <tr class="app-table-row">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $member->name }}</td>
                                <td>{{ $member->email }}{{ $member->phone ? ' · '.$member->phone : '' }}</td>
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
                                    @if ($managedRole === 'nurse')
                                        <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-teal-700 dark:text-teal-300" @click="accessOpen = !accessOpen" :aria-expanded="accessOpen.toString()">
                                            <span class="transition-transform" :class="accessOpen ? 'rotate-90' : ''">▶</span>
                                            <span x-text="accessOpen ? 'Hide access' : 'Customize access'">Customize access</span>
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-500">Not applicable</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="relative inline-block text-left" @click.outside="actionsOpen = false">
                                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 text-lg leading-none text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="Open account actions" :aria-expanded="actionsOpen.toString()" @click.prevent.stop="actionsOpen = !actionsOpen; if (actionsOpen) { const button = $event.currentTarget.getBoundingClientRect(); actionsStyle = `top: ${button.bottom + 8}px; left: ${Math.max(8, button.right - 192)}px`; }">
                                            <span aria-hidden="true">&hellip;</span>
                                        </button>
                                        <div x-show="actionsOpen" x-cloak x-transition x-bind:style="actionsStyle" class="fixed z-50 w-48 origin-top-right rounded-lg border border-slate-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900" role="menu">
                                            @if ($member->isArchived())
                                                <form method="POST" action="{{ route('nurses.restore', $member) }}">
                                                    @csrf
                                                    <button type="submit" class="block w-full rounded-md px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-zinc-800" role="menuitem">Restore</button>
                                                </form>
                                            @elseif ($member->invitation_accepted_at)
                                                @if ($member->email)
                                                    <form method="POST" action="{{ route('users.password-link', $member) }}">@csrf<input type="hidden" name="channel" value="email"><button type="submit" class="block w-full rounded-md px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-zinc-800" role="menuitem">Reset by email</button></form>
                                                @endif
                                                @if ($member->phone)
                                                    <form method="POST" action="{{ route('users.password-link', $member) }}">@csrf<input type="hidden" name="channel" value="sms"><button type="submit" class="block w-full rounded-md px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-zinc-800" role="menuitem">Reset by text</button></form>
                                                @endif
                                                <form method="POST" action="{{ route('nurses.toggle', $member) }}">
                                                    @csrf
                                                    <button type="submit" class="block w-full rounded-md px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-zinc-800" role="menuitem">{{ $member->is_active ? 'Deactivate' : 'Activate' }}</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('nurses.setup-link', $member) }}">
                                                    @csrf
                                                    <button type="submit" class="block w-full rounded-md px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-zinc-800" role="menuitem">Resend link</button>
                                                </form>
                                            @endif

                                            @unless ($member->isArchived())
                                                <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40" role="menuitem" @click.prevent.stop="actionsOpen = false; archiveAction = @js(route('nurses.destroy', $member)); archiveName = @js($member->name); archiveOpen = true">Archive</button>
                                            @endunless
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @if ($managedRole === 'nurse')
                                <tr x-show="accessOpen" x-cloak>
                                    <td colspan="8" class="border-t border-slate-200 bg-slate-50 p-0 dark:border-zinc-700 dark:bg-zinc-950/60">
                                        <div class="w-full p-4 sm:p-5">
                                            <div class="mb-4 flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-950 dark:text-white">Nurse access</p>
                                                    <p class="text-xs text-slate-500 dark:text-zinc-400">Choose the capabilities available to {{ $member->name }}.</p>
                                                </div>
                                                @php
                                                    $hiddenPermissions = \App\Models\User::hiddenNursePermissionKeys();
                                                    $visiblePermissionKeys = array_values(array_diff(
                                                        array_keys(\App\Models\User::nursePermissionDefinitions()),
                                                        $hiddenPermissions,
                                                    ));
                                                @endphp
                                                <span class="text-xs text-slate-500 dark:text-zinc-400">{{ collect($visiblePermissionKeys)->filter(fn ($permission) => $member->hasNursePermission($permission))->count() }} of {{ count($visiblePermissionKeys) }} enabled</span>
                                            </div>
                                            <form method="POST" action="{{ route('nurses.permissions.update', $member) }}" class="grid gap-5 lg:grid-cols-3">
                                                @csrf
                                                @method('PUT')
                                                @foreach (\App\Models\User::nursePermissionGroups() as $module => $permissions)
                                                    @continue(collect(array_keys($permissions))->diff($hiddenPermissions)->isEmpty())
                                                    <fieldset class="rounded-xl border border-slate-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                                        <legend class="px-1 text-sm font-semibold text-slate-950 dark:text-white">{{ $module }}</legend>
                                                        <div class="mt-2 grid gap-2">
                                                            @foreach ($permissions as $permission => $label)
                                                                @continue(in_array($permission, $hiddenPermissions, true))
                                                                <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm dark:border-zinc-700">
                                                                    <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked($member->hasNursePermission($permission))>
                                                                    <span>{{ $label }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </fieldset>
                                                @endforeach
                                                <button class="app-button-primary lg:col-span-3">Save access</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="8" class="px-4 py-8 text-center text-zinc-500">No accounts yet.</td></tr></tbody>
                    @endforelse
                </table>
                </div>
            </div>

            {{ $staff->links() }}
        </section>

        <div x-show="inviteOpen" x-cloak x-on:keydown.escape.window="inviteOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="invite-staff-title">
                <div class="app-panel w-full max-w-lg" @click.stop>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="eyebrow">Administration</p>
                            <h2 id="invite-staff-title" class="app-card-title">{{ $managedRole === 'barangay_admin' ? 'Invite Barangay Admin' : 'Invite nurse' }}</h2>
                        </div>
                        <button type="button" class="text-2xl leading-none text-slate-500 hover:text-slate-950 dark:hover:text-white" aria-label="Close invite dialog" @click="inviteOpen = false">&times;</button>
                    </div>
                    <form method="POST" action="{{ route('nurses.store') }}" class="mt-5 grid gap-4">
                        @csrf
                        <p class="text-sm text-slate-600 dark:text-zinc-300">{{ $managedRole === 'barangay_admin' ? 'The Barangay Admin receives an email link to set their password. They can manage nurses for their assigned barangay.' : 'The nurse receives an email link to set their password. Until then, the account stays pending.' }}</p>
                        <x-form-field label="Name" name="name" />
                        <x-form-field label="Email" name="email" type="email" />
                        <x-form-field label="Phone number" name="phone" type="tel" />
                        @if ($managedRole === 'barangay_admin')
                            <x-form-field label="Existing barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" />
                            <x-form-field label="Or new barangay" name="barangay_name" />
                        @else
                            <div class="rounded-lg border border-teal-100 bg-teal-50 p-3 text-sm text-teal-950 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-100">
                                Assigned barangay: <span class="font-semibold">{{ auth()->user()->barangay?->name }}</span>
                            </div>
                        @endif
                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="button" class="app-button-secondary" @click="inviteOpen = false">Cancel</button>
                            <button class="app-button-primary">Send password setup link</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div x-show="archiveOpen" x-cloak x-on:keydown.escape.window="archiveOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="archive-staff-title">
            <div class="app-panel w-full max-w-md" @click.stop>
                <p class="eyebrow">Administration</p>
                <h2 id="archive-staff-title" class="app-card-title mt-1">Archive staff account</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">Archive <span class="font-semibold" x-text="archiveName"></span>? The account will no longer be active.</p>
                <form method="POST" x-bind:action="archiveAction" class="mt-5 grid gap-4">
                    @csrf
                    @method('DELETE')
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Reason</span><select name="archive_reason" class="app-input" required><option value="">Choose a reason</option><option value="Retired">Retired</option><option value="Left RHU">Left RHU</option><option value="Transferred">Transferred</option><option value="Other">Other</option></select></label>
                    <div class="flex justify-end gap-2"><button type="button" class="app-button-secondary" @click="archiveOpen = false">Cancel</button><button class="app-button-danger">Archive account</button></div>
                </form>
            </div>
        </div>
    </div>
