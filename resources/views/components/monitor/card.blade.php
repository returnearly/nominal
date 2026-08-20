@props([
    'monitor',
    'checks',
])

@php
    $checks = collect($checks)->values();
    $group = filled($monitor->group) ? $monitor->group : 'Ungrouped';
@endphp

<x-monitor.history-styles />

<article class="nm-card" {{ $attributes }}>
    <header class="nm-card-header">
        <div class="nm-card-copy">
            <h3 class="nm-card-name">{{ $monitor->name }}</h3>
            <p class="nm-card-meta">
                <span>{{ $group }}</span>
                <span class="nm-card-dot">•</span>
                <span>{{ $monitor->target }}</span>
            </p>
        </div>
        <x-monitor.status-badge :status="$monitor->status" :enabled="$monitor->enabled" />
    </header>

    <div class="nm-card-chart">
        <x-monitor.heartbeat :checks="$checks" :show-range="true" />
    </div>
</article>
