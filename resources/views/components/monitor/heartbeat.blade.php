@props([
    'checks',
    'slots' => 20,
    'showLatency' => false,
])

@php
    $checks = collect($checks)->values();
    $pad = max(0, $slots - $checks->count());
    $maxLatency = max(1, (int) $checks->max('latency_ms'));
@endphp

<x-monitor.history-styles />

<div data-heartbeat class="nm-heartbeat" {{ $attributes }}>
    @for ($i = 0; $i < $pad; $i++)
        <span data-check="empty" class="nm-beat-empty" title="No check yet"></span>
    @endfor

    @foreach ($checks as $check)
        <span
            data-check="{{ $check->success ? 'up' : 'down' }}"
            class="{{ $check->success ? 'nm-beat-up' : 'nm-beat-down' }}"
            title="{{ $check->checked_at?->toDateTimeString() }} · {{ $check->success ? 'Up' : 'Down' }}{{ $check->latency_ms !== null ? ' · '.$check->latency_ms.' ms' : '' }}{{ filled($check->probe?->name) ? ' · '.$check->probe->name : '' }}"
        ></span>
    @endforeach
</div>

@if ($showLatency)
    <div data-latency class="nm-latency">
        @for ($i = 0; $i < $pad; $i++)
            <span data-check="empty" class="nm-beat-empty" title="No check yet"></span>
        @endfor

        @foreach ($checks as $check)
            <span
                data-check="{{ $check->success ? 'up' : 'down' }}"
                class="{{ $check->success ? 'nm-beat-up' : 'nm-beat-down' }}"
                title="{{ $check->latency_ms !== null ? $check->latency_ms.' ms' : 'No latency' }}"
                style="height: {{ max(8, (int) round((($check->latency_ms ?? 0) / $maxLatency) * 100)) }}%"
            ></span>
        @endforeach
    </div>
@endif
