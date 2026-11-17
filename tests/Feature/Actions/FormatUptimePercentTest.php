<?php

declare(strict_types=1);

use App\Actions\FormatUptimePercent;
use App\Enums\UptimeWindow;

it('formats uptime with three decimal places', function () {
    expect(FormatUptimePercent::make()->handle(100))->toBe('100.000%')
        ->and(FormatUptimePercent::make()->handle(99.996))->toBe('99.996%')
        ->and(FormatUptimePercent::make()->handle(99.9324))->toBe('99.932%')
        ->and(FormatUptimePercent::make()->handle(66.6667))->toBe('66.667%')
        ->and(FormatUptimePercent::make()->handle(0))->toBe('0.000%');
});

it('appends estimated offline duration for a window', function () {
    expect(FormatUptimePercent::make()->handle(100, UptimeWindow::ThirtyDays))->toBe('100.000% - [0s]')
        ->and(FormatUptimePercent::make()->handle(99.932, UptimeWindow::ThirtyDays))->toBe('99.932% - [29m23s]')
        ->and(FormatUptimePercent::make()->handle(0, UptimeWindow::OneHour))->toBe('0.000% - [1h]')
        ->and(FormatUptimePercent::make()->handle(50, UptimeWindow::OneHour))->toBe('50.000% - [30m]');
});

it('formats multi-unit offline durations compactly', function () {
    // 2h5m of a 24h window => 125 / 1440 ≈ 8.6806% downtime => 91.3194% uptime
    expect(FormatUptimePercent::make()->handle(91.3194, UptimeWindow::TwentyFourHours))
        ->toBe('91.319% - [2h5m]');
});

it('returns null when uptime is missing', function () {
    expect(FormatUptimePercent::make()->handle(null))->toBeNull()
        ->and(FormatUptimePercent::make()->handle(null, UptimeWindow::OneHour))->toBeNull();
});
