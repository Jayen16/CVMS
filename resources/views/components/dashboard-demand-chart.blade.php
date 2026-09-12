@props(['data' => []])

@php
    $chartWidth = 720;
    $plotLeft = 205;
    $plotWidth = 450;
    $rowHeight = 52;
    $chartHeight = max(180, count($data) * $rowHeight + 36);
    $max = max(1, (int) collect($data)->flatMap(fn ($item) => [$item['demand'], $item['stock']])->max());
@endphp

<section class="app-card overflow-hidden">
    <div class="app-card-header flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="app-card-title">Demand versus available stock</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Compare projected vaccine demand with current inventory.</p>
        </div>
        <div class="flex flex-wrap gap-4 text-xs text-slate-600 dark:text-zinc-300">
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-teal-500"></span>Estimated demand</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-sky-500"></span>Available stock</span>
        </div>
    </div>

    <div class="overflow-x-auto p-5" role="img" aria-label="Demand versus available stock bar chart">
        @if (count($data))
            <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="min-w-[680px] w-full" aria-hidden="true">
                @foreach ([0, 25, 50, 75, 100] as $tick)
                    @php $x = $plotLeft + ($plotWidth * $tick / 100); @endphp
                    <line x1="{{ $x }}" y1="8" x2="{{ $x }}" y2="{{ $chartHeight - 20 }}" class="stroke-slate-200 dark:stroke-zinc-800" stroke-width="1" />
                    <text x="{{ $x }}" y="{{ $chartHeight - 5 }}" text-anchor="middle" class="fill-slate-400 dark:fill-zinc-500" font-size="10">{{ round($max * $tick / 100) }}</text>
                @endforeach

                @foreach ($data as $index => $item)
                    @php
                        $y = 12 + ($index * $rowHeight);
                        $demandWidth = $plotWidth * ((int) $item['demand'] / $max);
                        $stockWidth = $plotWidth * ((int) $item['stock'] / $max);
                    @endphp
                    <text x="{{ $plotLeft - 12 }}" y="{{ $y + 18 }}" text-anchor="end" class="fill-slate-700 dark:fill-zinc-300" font-size="11">{{ str($item['label'])->limit(25) }}</text>
                    <rect x="{{ $plotLeft }}" y="{{ $y }}" width="{{ max(2, $demandWidth) }}" height="12" rx="4" class="fill-teal-500 dark:fill-teal-400" />
                    <rect x="{{ $plotLeft }}" y="{{ $y + 18 }}" width="{{ max(2, $stockWidth) }}" height="12" rx="4" class="{{ $item['status'] === 'shortage' ? 'fill-red-500' : 'fill-sky-500' }}" />
                    <text x="{{ $plotLeft + $demandWidth + 6 }}" y="{{ $y + 10 }}" class="fill-slate-700 dark:fill-zinc-200" font-size="10" font-weight="600">{{ $item['demand'] }}</text>
                    <text x="{{ $plotLeft + $stockWidth + 6 }}" y="{{ $y + 28 }}" class="fill-slate-700 dark:fill-zinc-200" font-size="10" font-weight="600">{{ $item['stock'] }}</text>
                @endforeach
            </svg>
        @else
            <p class="py-8 text-center text-sm text-zinc-500">No forecast data available.</p>
        @endif
    </div>
</section>
