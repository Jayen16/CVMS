<x-layouts::app :title="__('Submitted vaccine proof')">
    <div class="mx-auto grid w-full max-w-4xl gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-zinc-500">{{ $record->child->full_name }}</p>
                <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Submitted vaccine proof</h1>
                <p class="text-sm text-zinc-500">Photo {{ $proofIndex }} of {{ $proofCount }}</p>
            </div>
            <a href="{{ url()->previous() }}" class="app-button-secondary">Close</a>
        </div>

        <div class="app-panel grid gap-5">
            <div class="flex min-h-96 items-center justify-center rounded-lg bg-zinc-950 p-3">
                <img
                    src="{{ route('vaccinations.proofs.show', ['record' => $record, 'proofIndex' => $proofIndex]) }}"
                    alt="Submitted vaccine proof photo {{ $proofIndex }}"
                    class="max-h-[70vh] max-w-full object-contain"
                >
            </div>

            @if ($proofCount > 1)
                <div class="flex items-center justify-between gap-3">
                    @if ($proofIndex > 1)
                        <a href="{{ route('vaccinations.proofs.view', ['record' => $record, 'proof' => $proofIndex - 1]) }}" class="app-button-secondary">Previous</a>
                    @else
                        <span></span>
                    @endif

                    @if ($proofIndex < $proofCount)
                        <a href="{{ route('vaccinations.proofs.view', ['record' => $record, 'proof' => $proofIndex + 1]) }}" class="app-button-primary">Next</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
