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
                <p class="page-subtitle">Assign each nurse to the barangay where they record child vaccination data.</p>
            </div>

            <div class="app-card">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Barangay</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($nurses as $nurse)
                            <tr class="app-table-row">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $nurse->name }}</td>
                                <td>{{ $nurse->email }}</td>
                                <td>{{ $nurse->barangay?->name ?? 'Unassigned' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-zinc-500">No nurses yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $nurses->links() }}
        </section>

        <form method="POST" action="{{ route('nurses.store') }}" class="app-panel grid content-start gap-4">
            @csrf
            <h2 class="app-card-title">Add nurse</h2>
            <x-form-field label="Name" name="name" />
            <x-form-field label="Email" name="email" type="email" />
            <x-form-field label="Temporary password" name="password" type="password" />
            <x-form-field label="Existing barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" />
            <x-form-field label="Or new barangay" name="barangay_name" />
            <button class="app-button-primary">Create nurse</button>
        </form>
    </div>
</x-layouts::app>
