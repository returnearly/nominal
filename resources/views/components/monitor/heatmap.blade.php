@props([
    'cells',
])

<x-monitor.history-styles />

<div data-heatmap class="nm-heatmap">
    @foreach (collect($cells)->chunk(24) as $row)
        <div class="nm-heatmap-row">
            <span class="nm-heatmap-label">{{ $row->first()['start']->format('D j') }}</span>
            <div class="nm-heatmap-hours">
                @foreach ($row as $cell)
                    @php
                        $total = $cell['up'] + $cell['down'];
                        $state = match (true) {
                            $total === 0 => 'empty',
                            $cell['down'] === 0 => 'up',
                            $cell['up'] === 0 => 'down',
                            default => 'mixed',
                        };
                        $title = $cell['start']->format('D j, H:00');
                        $title .= $total === 0
                            ? ' · No checks'
                            : ' · '.$cell['up'].' up / '.$cell['down'].' down';
                        if ($cell['avg_latency_ms'] !== null) {
                            $title .= ' · '.$cell['avg_latency_ms'].' ms avg';
                        }
                    @endphp
                    <span data-hour="{{ $state }}" title="{{ $title }}"></span>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
