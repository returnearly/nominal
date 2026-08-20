@props([
    'monitor',
    'checks',
    'showTarget' => true,
    'showHeartbeat' => true,
    'name' => null,
])

@php
    $checks = collect($checks)->values();
    $group = filled($monitor->group) ? $monitor->group : 'Ungrouped';
    $name = $name ?? $monitor->public_display_name ?? $monitor->name;
@endphp

<x-monitor.history-styles />

<article class="nm-card" {{ $attributes }}>
    <header class="nm-card-header">
        <div class="nm-card-copy">
            <h3 class="nm-card-name">{{ $name }}</h3>
            @if ($showTarget)
                <p class="nm-card-meta">
                    <span>{{ $group }}</span>
                    <span class="nm-card-dot">•</span>
                    <span>{{ $monitor->target }}</span>
                </p>
            @elseif (filled($monitor->group))
                <p class="nm-card-meta">{{ $group }}</p>
            @endif
        </div>
        <x-monitor.status-badge :status="$monitor->status" :enabled="$monitor->enabled" />
    </header>

    @if ($showHeartbeat)
        <div class="nm-card-chart">
            <x-monitor.heartbeat :checks="$checks" :show-range="true" />
        </div>
    @endif
</article>
