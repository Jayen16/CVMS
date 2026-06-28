<x-layouts::app :title="$child->full_name.' vaccine card'">
    <div class="app-page">
        <div class="page-heading">
            <div>
                <a href="{{ route('children.show', $child) }}" class="text-sm text-teal-700 hover:underline dark:text-teal-300">Back to child profile</a>
                <h1 class="page-title mt-2">Digital child vaccine card</h1>
                <p class="page-subtitle">Download or present this QR-enabled card for quick clinic validation.</p>
            </div>
            <a href="{{ route('children.card.pdf', $child) }}" class="app-button-primary">Download PDF</a>
        </div>

        <section class="app-card">
            <div class="grid gap-6 lg:grid-cols-[1fr_240px]">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $child->full_name }}</h2>
                    <div class="mt-2 text-sm text-slate-600 dark:text-zinc-300">
                        {{ ucfirst($child->sex) }} | {{ $child->birthdate->format('M d, Y') }} | {{ $child->barangay?->name }}
                    </div>
                    <div class="mt-6 overflow-x-auto">
                        <table class="app-table">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 font-medium">Vaccine</th>
                                    <th class="px-4 py-3 font-medium">Dose</th>
                                    <th class="px-4 py-3 font-medium">Date</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($records as $record)
                                    <tr class="app-table-row">
                                        <td>{{ $record->vaccineType->name }}</td>
                                        <td>{{ $record->dose_number ?: 'Not set' }}</td>
                                        <td>{{ $record->administered_at->format('M d, Y') }}</td>
                                        <td>{{ ucfirst($record->verification_status) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No vaccine records yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <img src="{{ $qrCode }}" alt="Vaccine card QR code" class="mx-auto w-full max-w-[220px] rounded-lg bg-white p-3">
                    <p class="mt-4 text-xs leading-5 text-slate-600 dark:text-zinc-300">Scan this QR code at the clinic to open the validation page and confirm the child’s record quickly.</p>
                    <a href="{{ $validationUrl }}" class="mt-4 block break-all text-xs text-teal-700 hover:underline dark:text-teal-300">{{ $validationUrl }}</a>
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
