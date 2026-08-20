@props([
    'uptime',
    'windows' => null,
])

@php
    $format = \App\Actions\FormatUptimePercent::make();
    $windows = $windows ?? \App\Enums\UptimeWindow::cases();
@endphp

<div {{ $attributes->class('nm-uptime') }}>
    @foreach ($windows as $window)
        @php
            $percent = $uptime->percent($window);
            $tone = $percent === null
                ? 'empty'
                : ($percent >= 99.9 ? 'ok' : ($percent >= 95.0 ? 'warn' : 'bad'));
        @endphp
        <div class="nm-uptime-item" data-uptime-window="{{ $window->value }}">
            <span class="nm-metrics-label">{{ $window->label() }}</span>
            <span class="nm-uptime-value" data-uptime="{{ $tone }}">{{ $format->handle($percent) ?? '—' }}</span>
        </div>
    @endforeach
</div>
