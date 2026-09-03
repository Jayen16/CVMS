<x-layouts::app :title="__('Archive Center')">
<div class="app-page grid gap-6 lg:grid-cols-[380px_1fr]" x-data="{ archiveOpen: false, archiveForm: null }">
    @if (session('status'))
        <div class="app-alert-success lg:col-span-2">{{ session('status') }}</div>
    @endif

    <section class="app-panel h-fit">
        <p class="eyebrow">Administration</p>
        <h1 class="page-title">Archive Center</h1>
        <p class="page-subtitle">Archive a group of reports using one record type and date range. Archived data is retained and can be restored.</p>
        <form method="POST" action="{{ route('archives.store') }}" x-ref="archiveForm" class="mt-5 grid gap-4">
            @csrf
            <label class="grid gap-1.5 text-sm">
                <span class="font-medium">Record type</span>
                <select name="type" class="app-input" required>
                    <option value="">Choose a report type</option>
                    @foreach ($types as $type => $definition)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ $definition['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field label="Date from" name="date_from" type="date" :value="old('date_from')" />
                <x-form-field label="Date to" name="date_to" type="date" :value="old('date_to')" />
            </div>
            <label class="grid gap-1.5 text-sm">
                <span class="font-medium">Archive reason</span>
                <input name="archive_reason" class="app-input" value="{{ old('archive_reason') }}" placeholder="e.g. Completed reporting year" maxlength="100" required>
            </label>
            @error('type')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('date_from')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('date_to')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('archive_reason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                Archiving hides matching records from active reports. It does not permanently delete clinical data.
            </div>
            <button type="button" class="app-button-primary" @click="archiveOpen = true">Archive matching records</button>
        </form>
    </section>

    <div x-show="archiveOpen" x-cloak x-on:keydown.escape.window="archiveOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="archive-reports-title">
        <div class="app-panel w-full max-w-md" @click.stop>
            <p class="eyebrow">Data Management</p>
            <h2 id="archive-reports-title" class="app-card-title mt-1">Confirm archive</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">Archive all matching records for the selected type and date range? The data will be retained and can be restored.</p>
            <div class="mt-5 flex justify-end gap-2"><button type="button" class="app-button-secondary" @click="archiveOpen = false">Cancel</button><button type="button" class="app-button-danger" @click="$refs.archiveForm.requestSubmit()">Confirm archive</button></div>
        </div>
    </div>

    <section class="app-card overflow-x-auto">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-zinc-700">
            <h2 class="app-card-title">Archived reports</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">The latest 100 archived records available to your role.</p>
        </div>
        <table class="app-table">
            <thead><tr><th class="px-4 py-3">Type</th><th class="px-4 py-3">Record</th><th class="px-4 py-3">Record date</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Archived at</th><th class="px-4 py-3">Action</th></tr></thead>
            <tbody>
                @forelse ($archived as $item)
                    <tr class="app-table-row">
                        <td><span class="status-pill status-rejected">{{ $item['label'] }}</span></td>
                        <td class="font-medium">{{ $item['description'] }}</td>
                        <td>{{ $item['record_date'] }}</td>
                        <td>{{ $item['reason'] ?? '—' }}</td>
                        <td>{{ $item['archived_at'] }}</td>
                        <td>
                            <form method="POST" action="{{ route('archives.restore', [$item['type'], $item['id']]) }}" onsubmit="return confirm('Restore this report record?')">
                                @csrf
                                <button class="app-button-secondary !px-3 !py-1.5 !text-xs">Restore</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No archived reports found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
</x-layouts::app>
