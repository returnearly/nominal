@props([
    'checks',
])

@php
    $points = collect($checks)
        ->filter(fn ($check): bool => $check->latency_ms !== null)
        ->values();
    $max = max(1, (int) $points->max('latency_ms'));
    $count = $points->count();
    $width = 1000;
    $height = 100;
    $coords = $points->map(function ($check, int $index) use ($count, $width, $height, $max): array {
        $x = $count === 1 ? $width / 2 : ($index / ($count - 1)) * $width;
        $y = (1 - ($check->latency_ms / $max)) * $height;

        return [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'left' => round($x / $width * 100, 3),
            'top' => round($y / $height * 100, 3),
            'check' => $check,
        ];
    });
    $line = $coords->map(fn (array $point, int $index): string => ($index === 0 ? 'M' : 'L').$point['x'].' '.$point['y'])->implode(' ');
    $first = $coords->first();
    $last = $coords->last();
    $fill = $first && $last
        ? $line.' L '.$last['x'].' '.$height.' L '.$first['x'].' '.$height.' Z'
        : '';
    $ticks = [1, 0.5, 0];
@endphp

<x-monitor.history-styles />

@if ($coords->isEmpty())
    <p class="nm-empty">No latency samples yet.</p>
@else
    <div data-trend class="nm-trend">
        <div class="nm-trend-y" aria-hidden="true">
            @foreach ($ticks as $tick)
                <span style="top: {{ (1 - $tick) * 100 }}%">{{ (int) round($max * $tick) }}ms</span>
            @endforeach
        </div>
        <div class="nm-trend-body">
            <svg class="nm-trend-svg" viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" role="img" aria-label="Response time trend">
                @foreach ($ticks as $tick)
                    <line class="nm-trend-grid" x1="0" y1="{{ (1 - $tick) * $height }}" x2="{{ $width }}" y2="{{ (1 - $tick) * $height }}" />
                @endforeach
                <path class="nm-trend-fill" d="{{ $fill }}" />
                <path class="nm-trend-line" d="{{ $line }}" />
            </svg>
            <div class="nm-trend-hits">
                @foreach ($coords as $point)
                    @php $check = $point['check']; @endphp
                    <span
                        class="nm-trend-hit{{ $check->success ? '' : ' nm-trend-hit-down' }}"
                        style="left: {{ $point['left'] }}%; top: {{ $point['top'] }}%"
                    >
                        <span class="nm-trend-dot"></span>
                        <span class="nm-beat-tip" role="tooltip">
                            <span class="nm-beat-tip-k">TIMESTAMP</span>
                            <span>{{ $check->checked_at?->toDateTimeString() ?? 'Unknown' }}</span>
                            <span class="nm-beat-tip-k">RESPONSE TIME</span>
                            <span>{{ $check->latency_ms }}ms</span>
                            @if (filled($check->probe?->name))
                                <span class="nm-beat-tip-k">PROBE</span>
                                <span>{{ $check->probe->name }}</span>
                            @endif
                        </span>
                    </span>
                @endforeach
            </div>
        </div>
        <div class="nm-trend-x">
            <span>{{ $points->first()->checked_at?->format('M j, g:ia') }}</span>
            <span>{{ $points->last()->checked_at?->format('M j, g:ia') }}</span>
        </div>
    </div>
@endif
