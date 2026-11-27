<section class="app-card overflow-hidden" aria-labelledby="ai-planning-heading">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-5 sm:px-6 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300" aria-hidden="true"><flux:icon.chart-bar class="size-5" /></span>
                    <div>
                        <h2 id="ai-planning-heading" class="text-lg font-semibold text-slate-900 dark:text-white">AI Planning Summary</h2>
                        <p class="mt-1 max-w-xl text-sm leading-6 text-slate-600 dark:text-zinc-400">Summarize vaccine demand, highlight possible shortages, and suggest stock-planning actions for your team.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                <button type="button" wire:click="generateAiSummary" wire:loading.attr="disabled" @disabled($demand->isEmpty()) class="app-button-secondary shrink-0 disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="generateAiSummary">{{ $aiSummary ? 'Refresh summary' : 'Generate AI Summary' }}</span>
                    <span wire:loading.inline-flex wire:target="generateAiSummary" class="items-center gap-2"><span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-teal-700" aria-hidden="true"></span> Preparing summary...</span>
                </button>
                </div>
            </div>
            <div aria-live="polite" class="px-5 py-6 sm:px-6">
                @if ($aiError)
                    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $aiError }}</div>
                @elseif ($aiSummary)
                    <div class="mb-6 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-950/30">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">Potential shortages</p>
                            <p class="mt-2 text-2xl font-semibold text-amber-950 dark:text-amber-100">{{ $demand->filter(fn ($row) => $row['projected_balance'] < 0)->count() }} <span class="text-sm font-normal">vaccine types</span></p>
                        </div>
                        <div class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 dark:border-teal-900 dark:bg-teal-950/30">
                            <p class="text-xs font-semibold uppercase tracking-wide text-teal-800 dark:text-teal-300">Sufficient stock</p>
                            <p class="mt-2 text-2xl font-semibold text-teal-950 dark:text-teal-100">{{ $demand->filter(fn ($row) => $row['projected_balance'] >= 0)->count() }} <span class="text-sm font-normal">vaccine types</span></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-zinc-400">Planning period</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $forecastMonths }} <span class="text-sm font-normal">{{ $forecastMonths === 1 ? 'month' : 'months' }}</span></p>
                        </div>
                    </div>
                    <div class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs leading-5 text-slate-500 dark:text-zinc-400">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">AI-generated overview</span>
                        <span>Updated {{ $aiGeneratedAt }}</span>
                    </div>
                    <div class="max-w-3xl text-base leading-7 text-slate-700 dark:text-zinc-200 [&_h3]:mb-3 [&_h3]:mt-7 [&_h3]:border-b [&_h3]:border-slate-100 [&_h3]:pb-2 [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-slate-900 dark:[&_h3]:border-zinc-800 dark:[&_h3]:text-white [&_h3:first-child]:mt-0 [&_p]:mb-4 [&_ul]:mb-5 [&_ul]:list-disc [&_ul]:space-y-3 [&_ul]:pl-5 [&_ol]:mb-5 [&_ol]:list-decimal [&_ol]:space-y-3 [&_ol]:pl-5 [&_strong]:font-semibold [&_strong]:text-slate-900 dark:[&_strong]:text-white [&_a]:underline">
                        {!! \Illuminate\Support\Str::markdown($aiSummary, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                    </div>
                @else
                    <div class="rounded-xl border border-dashed border-slate-200 px-5 py-6 text-center dark:border-zinc-700">
                        <p class="text-sm font-medium text-slate-700 dark:text-zinc-200">Your planning overview will appear here</p>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-zinc-400">{{ $demand->isEmpty() ? 'No demand data is available for this forecast. Add vaccination records or choose another forecast period.' : 'Select Generate AI Summary to see stock priorities and suggested next steps here. Internet access is required.' }}</p>
                    </div>
                @endif
            </div>
            <div class="border-t border-slate-100 bg-slate-50/70 px-5 py-4 sm:px-6 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs leading-5 text-slate-500 dark:text-zinc-400"><span class="font-semibold text-slate-700 dark:text-zinc-300">Review before acting.</span> Check the summary against your demand forecast and stock records. Figures are estimates for administrative planning; clinical decisions remain with health workers.</p>
            </div>
        </section>
