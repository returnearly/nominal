@props([
    'checks',
    'slots' => 40,
    'showStatus' => true,
    'showLatency' => false,
    'showRange' => false,
])

@php
    $checks = collect($checks)->values();
    $pad = max(0, $slots - $checks->count());
    $maxLatency = max(1, (int) $checks->max('latency_ms'));
    $oldest = $checks->first()?->checked_at;
    $newest = $checks->last()?->checked_at;
@endphp

<x-monitor.history-styles />

@if ($showStatus)
    <div data-heartbeat {{ $attributes->class('nm-heartbeat') }}>
        @for ($i = 0; $i < $pad; $i++)
            <span data-check="empty" class="nm-beat-empty" title="No check yet"></span>
        @endfor

        @foreach ($checks as $check)
            <span
                data-check="{{ $check->success ? 'up' : 'down' }}"
                class="{{ $check->success ? 'nm-beat-up' : 'nm-beat-down' }}"
            >
                <x-monitor.check-tip :check="$check" />
            </span>
        @endforeach
    </div>
@endif

@if ($showLatency)
    <div data-latency class="nm-latency">
        @for ($i = 0; $i < $pad; $i++)
            <span data-check="empty" class="nm-beat-empty" title="No check yet"></span>
        @endfor

        @foreach ($checks as $check)
            <span
                data-check="{{ $check->success ? 'up' : 'down' }}"
                class="{{ $check->success ? 'nm-beat-up' : 'nm-beat-down' }}"
                style="height: {{ max(8, (int) round((($check->latency_ms ?? 0) / $maxLatency) * 100)) }}%"
            >
                <x-monitor.check-tip :check="$check" />
            </span>
        @endforeach
    </div>
@endif

@if ($showRange && $oldest && $newest)
    <div class="nm-range">
        <span>{{ $oldest->diffForHumans() }}</span>
        <span>{{ $newest->diffForHumans() }}</span>
    </div>
@endif
