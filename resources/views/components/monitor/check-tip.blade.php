@props([
    'check',
])

<span class="nm-beat-tip" role="tooltip">
    <span class="nm-beat-tip-k">TIMESTAMP</span>
    <span>{{ $check->checked_at?->toDateTimeString() ?? 'Unknown' }}</span>
    <span class="nm-beat-tip-k">RESPONSE TIME</span>
    <span>{{ \App\Actions\FormatMilliseconds::make()->handle($check->latency_ms) ?? 'n/a' }}</span>
    @if (filled($check->message))
        <span class="nm-beat-tip-k">ERROR</span>
        <span>{{ $check->message }}</span>
    @endif
    @if (filled($check->probe?->name))
        <span class="nm-beat-tip-k">PROBE</span>
        <span>{{ $check->probe->name }}</span>
    @endif
    @if (! empty($check->condition_results))
        <span class="nm-beat-tip-k">CONDITIONS</span>
        @foreach ($check->condition_results as $condition)
            <span>{{ ($condition['passed'] ?? false) ? '✓' : '✗' }} {{ $condition['expression'] ?? '' }}</span>
        @endforeach
    @endif
</span>
