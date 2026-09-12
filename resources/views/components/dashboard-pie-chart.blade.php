@props(['title', 'subtitle' => null, 'data' => []])

@php
    $total = max(1, collect($data)->sum('value'));
    $gradientStops = [];
    $offset = 0;

    foreach ($data as $item) {
        $end = $offset + (((int) $item['value'] / $total) * 100);
        $gradientStops[] = $item['color'].' '.$offset.'% '.$end.'%';
        $offset = $end;
    }
@endphp

<section class="app-card p-5">
    <div class="mb-5">
        <h2 class="app-card-title">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="flex min-h-[220px] items-center justify-center gap-8" role="img" aria-label="{{ $title }} pie chart">
        @if (collect($data)->sum('value') > 0)
            <div class="size-40 shrink-0 rounded-full" style="background: conic-gradient({{ implode(', ', $gradientStops) }});">
                <div class="m-8 flex size-24 items-center justify-center rounded-full bg-white text-center dark:bg-zinc-900">
                    <span class="text-2xl font-semibold text-slate-950 dark:text-white">{{ collect($data)->sum('value') }}</span>
                </div>
            </div>
            <div class="space-y-3">
                @foreach ($data as $item)
                    @php $percentage = round(((int) $item['value'] / $total) * 100); @endphp
                    <div class="flex items-center gap-2 text-sm">
                        <span class="size-3 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                        <span class="text-slate-600 dark:text-zinc-300">{{ $item['label'] }}</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $percentage }}% ({{ $item['value'] }})</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-zinc-500">No child sex data available yet.</p>
        @endif
    </div>
</section>
