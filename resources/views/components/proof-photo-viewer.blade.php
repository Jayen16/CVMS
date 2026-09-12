@props(['record'])

@php($proofImages = [])
@foreach ($record->proofPaths() as $proofPath)
    @php($proofImages[] = route('vaccinations.proofs.show', ['record' => $record, 'proofIndex' => $loop->iteration]))
@endforeach

<span x-data="{ open: false, images: @js($proofImages), index: 0 }">
    <a href="#" @click.prevent="open = true; index = 0" class="text-teal-700 hover:underline dark:text-teal-300">
        View submitted {{ count($proofImages) }} photo{{ count($proofImages) === 1 ? '' : 's' }}
    </a>

    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" x-transition.opacity @keydown.escape.window="open = false">
        <div @click.outside="open = false" class="w-full max-w-4xl rounded-2xl bg-white p-4 shadow-xl dark:bg-zinc-900 sm:p-6" role="dialog" aria-modal="true">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Submitted vaccine proof</h2>
                    <p class="text-sm text-zinc-500" x-text="`Photo ${index + 1} of ${images.length}`"></p>
                </div>
                <button type="button" class="app-button-secondary" @click="open = false">Close</button>
            </div>
            <div class="mt-4 flex min-h-96 items-center justify-center rounded-lg bg-zinc-950 p-3">
                <img :src="images[index]" :alt="`Submitted vaccine proof photo ${index + 1}`" class="max-h-[70vh] max-w-full object-contain">
            </div>
            <div x-show="images.length > 1" class="mt-4 flex items-center justify-between gap-3">
                <button type="button" class="app-button-secondary" :disabled="index === 0" @click="index = Math.max(0, index - 1)">Previous</button>
                <button type="button" class="app-button-primary" :disabled="index === images.length - 1" @click="index = Math.min(images.length - 1, index + 1)">Next</button>
            </div>
        </div>
    </div>
</span>
