@props(['title', 'subtitle' => null, 'data' => [], 'suffix' => '', 'orientation' => 'vertical'])

@php
    $max = max(1, (int) collect($data)->max('value'));
    $chartWidth = 640;
    $chartHeight = 280;
    $plotLeft = 48;
    $plotTop = 20;
    $plotWidth = 560;
    $plotHeight = 190;
    $count = max(1, count($data));
    $slotWidth = (float) $plotWidth / $count;
    $horizontalHeight = max(220, count($data) * 42 + 40);
@endphp

<section class="app-card p-5">
    <div class="mb-5">
        <h2 class="app-card-title">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ $subtitle }}</p>
        @endif
    </div>

    @if (count($data))
        <div class="overflow-x-auto" role="img" aria-label="{{ $title }} bar chart">
            @if ($orientation === 'horizontal')
                <svg viewBox="0 0 {{ $chartWidth }} {{ $horizontalHeight }}" class="w-full" aria-hidden="true">
                    @foreach ([0, 25, 50, 75, 100] as $tick)
                        @php $x = 150 + (460 * $tick / 100); @endphp
                        <line x1="{{ $x }}" y1="20" x2="{{ $x }}" y2="{{ $horizontalHeight - 20 }}" class="stroke-slate-200 dark:stroke-zinc-800" stroke-width="1" />
                        <text x="{{ $x }}" y="{{ $horizontalHeight - 4 }}" text-anchor="middle" class="fill-slate-400 dark:fill-zinc-500" font-size="11">{{ round($max * $tick / 100) }}</text>
                    @endforeach
                    @foreach ($data as $index => $item)
                        @php
                            $y = 24 + ($index * 42);
                            $barWidth = 460 * ((int) $item['value'] / (int) $max);
                        @endphp
                        <text x="140" y="{{ $y + 15 }}" text-anchor="end" class="fill-slate-600 dark:fill-zinc-300" font-size="12">{{ $item['label'] }}</text>
                        <rect x="150" y="{{ $y }}" width="{{ max(2, $barWidth) }}" height="22" rx="5" class="fill-teal-500 dark:fill-teal-400" />
                        <text x="{{ 158 + $barWidth }}" y="{{ $y + 15 }}" class="fill-slate-700 dark:fill-zinc-200" font-size="12" font-weight="600">{{ $item['value'] }}{{ $suffix }}</text>
                    @endforeach
                </svg>
            @else
            <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="min-w-[520px] w-full" aria-hidden="true">
                @foreach ([0, 25, 50, 75, 100] as $tick)
                    @php $y = $plotTop + $plotHeight - ($plotHeight * $tick / 100); @endphp
                    <line x1="{{ $plotLeft }}" y1="{{ $y }}" x2="{{ $plotLeft + $plotWidth }}" y2="{{ $y }}" class="stroke-slate-200 dark:stroke-zinc-800" stroke-width="1" />
                    <text x="{{ $plotLeft - 10 }}" y="{{ $y + 4 }}" text-anchor="end" class="fill-slate-400 dark:fill-zinc-500" font-size="11">{{ round($max * $tick / 100) }}</text>
                @endforeach

                <line x1="{{ $plotLeft }}" y1="{{ $plotTop }}" x2="{{ $plotLeft }}" y2="{{ $plotTop + $plotHeight }}" class="stroke-slate-300 dark:stroke-zinc-700" stroke-width="1.5" />
                <line x1="{{ $plotLeft }}" y1="{{ $plotTop + $plotHeight }}" x2="{{ $plotLeft + $plotWidth }}" y2="{{ $plotTop + $plotHeight }}" class="stroke-slate-300 dark:stroke-zinc-700" stroke-width="1.5" />

                @foreach ($data as $index => $item)
                    @php
                        $barHeight = (float) $plotHeight * ((int) $item['value'] / (int) $max);
                        $barWidth = min(72, (float) $slotWidth * 0.58);
                        $x = $plotLeft + ($slotWidth * (int) $index) + (($slotWidth - $barWidth) / 2);
                        $y = $plotTop + $plotHeight - $barHeight;
                        $label = str($item['label'])->limit(16);
                    @endphp
                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barWidth }}" height="{{ max(2, $barHeight) }}" rx="5" class="fill-teal-500 dark:fill-teal-400" />
                    <text x="{{ $x + ($barWidth / 2) }}" y="{{ max(14, $y - 8) }}" text-anchor="middle" class="fill-slate-700 dark:fill-zinc-200" font-size="12" font-weight="600">{{ $item['value'] }}{{ $suffix }}</text>
                    <text x="{{ $x + ($barWidth / 2) }}" y="{{ $plotTop + $plotHeight + 24 }}" text-anchor="middle" class="fill-slate-500 dark:fill-zinc-400" font-size="11">{{ $label }}</text>
                @endforeach
            </svg>
            @endif
        </div>
    @else
        <p class="text-sm text-zinc-500">No data available yet.</p>
    @endif
</section>
