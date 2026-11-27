@props(['title', 'description', 'action', 'summary' => null, 'generatedAt' => null, 'error' => null, 'empty' => false, 'scope' => null])

@php
    // Split the requested Markdown headings into presentation panels. Each panel
    // still goes through the safe Markdown renderer, including older summaries.
    $sections = [];
    if ($summary) {
        $parts = preg_split('/^#{2,3}\s+(.+?)\s*$/m', $summary, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($parts) > 1) {
            if (trim($parts[0]) !== '') {
                $sections[] = ['title' => 'Overview', 'body' => trim($parts[0]), 'type' => 'overview'];
            }
            for ($index = 1; $index + 1 < count($parts); $index += 2) {
                $heading = trim($parts[$index]);
                $headingKey = strtolower(trim($heading, " *:"));
                $sections[] = [
                    'title' => trim($heading, ' *:'),
                    'body' => trim($parts[$index + 1]),
                    'type' => match ($headingKey) {
                        'overview', 'what this means', 'clinic snapshot' => 'overview',
                        'records to review' => 'records',
                        'why it needs review', 'what needs attention' => 'explanation',
                        'suggested follow-up', 'what to do next', 'suggested work plan' => 'actions',
                        default => 'additional',
                    },
                ];
            }
        } else {
            $sections[] = ['title' => 'Overview', 'body' => $summary, 'type' => 'overview'];
        }
    }
@endphp

<section class="app-card overflow-hidden" aria-label="{{ $title }}">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6 dark:border-zinc-800">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200" aria-hidden="true"><flux:icon.clipboard-document-list class="size-5" /></span>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-teal-700 dark:text-teal-300">Record review assistant</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                <p class="mt-1 max-w-xl text-sm leading-6 text-slate-500 dark:text-zinc-400">{{ $description }}</p>
                @if ($scope)<p class="mt-2 inline-flex rounded-md bg-teal-50 px-2 py-1 text-xs font-medium text-teal-800 dark:bg-teal-950 dark:text-teal-200">{{ $scope }}</p>@endif
            </div>
        </div>
        <button type="button" wire:click="{{ $action }}" wire:loading.attr="disabled" @disabled($empty) class="app-button-secondary shrink-0 disabled:cursor-not-allowed disabled:opacity-50">
            <span wire:loading.remove wire:target="{{ $action }}" class="inline-flex items-center gap-2"><flux:icon.arrow-path class="size-4" />{{ $error ? 'Try again' : ($summary ? 'Refresh summary' : ($action === 'generateAiFollowUpBriefing' ? 'Generate follow-up briefing' : 'Summarize record')) }}</span>
            <span wire:loading.inline-flex wire:target="{{ $action }}" class="items-center gap-2"><span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-teal-700" aria-hidden="true"></span> Preparing...</span>
        </button>
    </div>
    <div class="px-5 py-5 sm:px-6" aria-live="polite">
        @if ($error)
            <div role="alert" class="rounded-xl bg-red-50 p-4 text-sm leading-6 text-red-800 dark:bg-red-950/30 dark:text-red-200">
                <p class="font-semibold">{{ $action === 'generateAiFollowUpBriefing' ? 'Unable to generate the follow-up briefing' : 'Unable to generate the record summary' }}</p>
                <p class="mt-1">{{ $error }}</p>
            </div>
        @elseif ($summary)
            <div class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs leading-5 text-slate-500 dark:text-zinc-400">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-2.5 py-1 font-medium text-teal-800 dark:bg-teal-950 dark:text-teal-200"><span class="size-1.5 rounded-full bg-teal-500" aria-hidden="true"></span>AI-generated overview</span>
                <span>Updated {{ $generatedAt }}</span>
            </div>
            <div class="ai-review-grid">
                @foreach ($sections as $section)
                    <section class="ai-review-panel ai-review-panel--{{ $section['type'] }}">
                        <div class="mb-4 flex items-center gap-2.5">
                            <span class="ai-review-section-icon" aria-hidden="true">
                                @if ($section['type'] === 'actions')<flux:icon.clipboard-document-check class="size-4" />
                                @elseif ($section['type'] === 'records')<flux:icon.clipboard-document-list class="size-4" />
                                @else<flux:icon.information-circle class="size-4" />@endif
                            </span>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $section['title'] }}</h3>
                        </div>
                        <div class="ai-review-copy">
                            {!! \Illuminate\Support\Str::markdown($section['body'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-5 py-7 text-center dark:border-zinc-700 dark:bg-zinc-900/50">
                <p class="text-sm font-medium text-slate-700 dark:text-zinc-200">{{ $empty ? 'No matching records' : 'Your review checklist starts here' }}</p>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500 dark:text-zinc-400">{{ $empty ? 'Change the filters to generate a briefing.' : 'Generate a concise overview, records to check, and suggested follow-up. Only non-identifying counts and statuses are sent.' }}</p>
            </div>
        @endif
    </div>
    <p class="border-t border-slate-100 px-5 py-3 text-xs leading-5 text-slate-500 sm:px-6 dark:border-zinc-800 dark:text-zinc-400"><strong class="font-semibold">Review before acting.</strong> Verify the summary against the records. Missing records do not confirm missed vaccinations. This assistant supports administrative review and does not make clinical decisions or send reminders.</p>
</section>
