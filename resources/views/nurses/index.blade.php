<x-layouts::app :title="__('Nurses')">
    <div class="app-page grid gap-6 lg:grid-cols-[1fr_380px]">
        <section class="flex flex-col gap-4">
            @if (session('status'))
                <div class="app-alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <div>
                <p class="eyebrow">Administration</p>
                <h1 class="page-title">Nurse accounts</h1>
                <p class="page-subtitle">Invite nurses by email and assign each account to the barangay where they record child vaccination data.</p>
            </div>

            <div class="app-card overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Barangay</th>
                            <th class="px-4 py-3 font-medium">Setup</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($nurses as $nurse)
                            <tr class="app-table-row">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $nurse->name }}</td>
                                <td>{{ $nurse->email }}</td>
                                <td>{{ $nurse->barangay?->name ?? 'Unassigned' }}</td>
                                <td>
                                    @if ($nurse->invitation_accepted_at)
                                        <span class="status-pill status-verified">Configured</span>
                                    @else
                                        <span class="status-pill status-pending">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($nurse->invitation_accepted_at)
                                        <span class="status-pill {{ $nurse->is_active ? 'status-verified' : 'status-rejected' }}">
                                            {{ $nurse->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    @else
                                        <span class="text-xs font-medium text-slate-500">Waiting for setup</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($nurse->invitation_accepted_at)
                                            <form method="POST" action="{{ route('nurses.toggle', $nurse) }}">
                                                @csrf
                                                <button class="app-button-secondary !px-3 !py-1.5 !text-xs">{{ $nurse->is_active ? 'Deactivate' : 'Activate' }}</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('nurses.setup-link', $nurse) }}">
                                                @csrf
                                                <button class="app-button-secondary !px-3 !py-1.5 !text-xs">Resend link</button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('nurses.destroy', $nurse) }}" onsubmit="return confirm('Remove this nurse account?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="app-button-danger !px-3 !py-1.5 !text-xs">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No nurses yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $nurses->links() }}
        </section>

        <form method="POST" action="{{ route('nurses.store') }}" class="app-panel grid content-start gap-4">
            @csrf
            <h2 class="app-card-title">Invite nurse</h2>
            <p class="text-sm text-slate-600 dark:text-zinc-300">The nurse receives an email link to set their password. Until then, the account stays pending.</p>
            <x-form-field label="Name" name="name" />
            <x-form-field label="Email" name="email" type="email" />
            <x-form-field label="Existing barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" />
            <x-form-field label="Or new barangay" name="barangay_name" />
            <button class="app-button-primary">Send password setup link</button>
        </form>
    </div>
</x-layouts::app>
