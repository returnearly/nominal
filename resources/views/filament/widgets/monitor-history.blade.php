@php
    $group = filled($record->group) ? $record->group : 'Ungrouped';
    $range = $minLatencyMs !== null && $maxLatencyMs !== null
        ? $minLatencyMs.'–'.$maxLatencyMs.'ms'
        : '—';
@endphp

<x-monitor.history-styles />

<div class="nm-detail">
    <div class="nm-stat-cards">
        <div class="nm-panel nm-stat-card">
            <span class="nm-metrics-label">Current status</span>
            <span class="nm-metrics-value">{{ $statusLabel }}</span>
        </div>
        <div class="nm-panel nm-stat-card">
            <span class="nm-metrics-label">Avg. response</span>
            <span class="nm-metrics-value">{{ $averageLatencyMs !== null ? $averageLatencyMs.'ms' : '—' }}</span>
        </div>
        <div class="nm-panel nm-stat-card">
            <span class="nm-metrics-label">Response range</span>
            <span class="nm-metrics-value">{{ $range }}</span>
        </div>
        <div class="nm-panel nm-stat-card">
            <span class="nm-metrics-label">Last check</span>
            <span class="nm-metrics-value">{{ $record->last_checked_at?->diffForHumans() ?? 'Never' }}</span>
        </div>
    </div>

    <section class="nm-panel">
        <header class="nm-section-head">
            <div>
                <h3 class="nm-section-title">Recent checks</h3>
                <p class="nm-card-meta">
                    <span>{{ $group }}</span>
                    <span class="nm-card-dot">·</span>
                    <span>{{ $record->target }}</span>
                </p>
            </div>
            @if ($averageLatencyMs !== null)
                <span class="nm-card-latency">~{{ $averageLatencyMs }}ms</span>
            @endif
        </header>
        <x-monitor.heartbeat :checks="$checks" :show-range="true" />
        <p class="nm-detail-interval">Check every {{ $record->interval_seconds }} seconds</p>
    </section>

    <section class="nm-panel">
        <h3 class="nm-section-title">Response time</h3>
        <x-monitor.trend :checks="$checks" />
    </section>
</div>
