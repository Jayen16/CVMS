<x-layouts::app :title="__('Archived Children')">
<div class="app-page">
    @if (session('status'))
        <div class="app-alert-success mb-6">{{ session('status') }}</div>
    @endif

    <div class="page-heading">
        <div>
            <p class="eyebrow">Child Records</p>
            <h1 class="page-title">Archived child records</h1>
            <p class="page-subtitle">Archived records are hidden from daily operations. Vaccination history and audit history remain intact.</p>
        </div>
        {{-- <a href="{{ route('children.index') }}" class="app-button-secondary">Back to children</a> --}}
    </div>

    <div class="app-card overflow-x-auto">
        <table class="app-table">
            <thead>
                <tr>
                    <th class="px-4 py-3 font-medium">Child</th>
                    <th class="px-4 py-3 font-medium">Barangay</th>
                    <th class="px-4 py-3 font-medium">Reason</th>
                    <th class="px-4 py-3 font-medium">Archived by</th>
                    <th class="px-4 py-3 font-medium">Archived at</th>
                    <th class="px-4 py-3 font-medium">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($children as $child)
                    <tr class="app-table-row">
                        <td>
                            <span class="font-semibold">{{ $child->full_name }}</span>
                            <div class="text-xs text-zinc-500">{{ ucfirst($child->sex) }} | Born {{ $child->birthdate->format('M d, Y') }}</div>
                        </td>
                        <td>{{ $child->barangay?->name ?? 'Unassigned' }}</td>
                        <td><span class="status-pill status-rejected">{{ $child->archive_reason ?? 'No reason recorded' }}</span></td>
                        <td>{{ $child->archiver?->name ?? 'System' }}</td>
                        <td>{{ $child->archived_at?->format('M d, Y h:i A') }}</td>
                        <td>
                            <form method="POST" action="{{ route('children.restore', $child->id) }}" onsubmit="return confirm('Restore this child to the active registry?')">
                                @csrf
                                <button class="app-button-secondary !px-3 !py-1.5 !text-xs">Restore</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No archived child records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $children->links() }}
</div>
</x-layouts::app>
