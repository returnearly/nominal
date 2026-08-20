@php
    $group = filled($record->group) ? $record->group : 'Ungrouped';
    $format = \App\Actions\FormatMilliseconds::make();
    $averageLatency = $format->handle($averageLatencyMs);
    $range = $minLatencyMs !== null && $maxLatencyMs !== null
        ? $format->handle($minLatencyMs).'–'.$format->handle($maxLatencyMs)
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
            <span class="nm-metrics-value">{{ $averageLatency ?? '—' }}</span>
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

    @if ($heartbeatUrl = $record->heartbeatUrl())
        <section class="nm-panel">
            <span class="nm-metrics-label">Heartbeat URL</span>
            <div
                class="nm-copy-url"
                x-data="{ copied: false }"
            >
                <code class="nm-copy-url-value">{{ $heartbeatUrl }}</code>
                <button
                    type="button"
                    class="nm-copy-url-button"
                    x-on:click="
                        navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($heartbeatUrl) }});
                        copied = true;
                        setTimeout(() => copied = false, 1500)
                    "
                >
                    <span x-show="! copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied</span>
                </button>
            </div>
        </section>
    @endif

    <section class="nm-panel">
        <span class="nm-metrics-label">Badges</span>
        <div class="nm-badges">
            <img src="{{ $record->statusBadgeSvgUrl() }}" alt="Status badge">
            <img src="{{ $record->uptimeBadgeSvgUrl() }}" alt="Uptime badge">
            <img src="{{ $record->latencyBadgeSvgUrl() }}" alt="Latency badge">
        </div>
        <div
            class="nm-copy-url"
            x-data="{ copied: false }"
        >
            <code class="nm-copy-url-value">{{ $record->badgeMarkdown() }}</code>
            <button
                type="button"
                class="nm-copy-url-button"
                x-on:click="
                    navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($record->badgeMarkdown()) }});
                    copied = true;
                    setTimeout(() => copied = false, 1500)
                "
            >
                <span x-show="! copied">Copy markdown</span>
                <span x-show="copied" x-cloak>Copied</span>
            </button>
        </div>
    </section>

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
            @if ($averageLatency !== null)
                <span class="nm-card-latency">~{{ $averageLatency }}</span>
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
