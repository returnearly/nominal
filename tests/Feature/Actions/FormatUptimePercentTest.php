<?php

declare(strict_types=1);

use App\Actions\FormatUptimePercent;

it('formats uptime with two decimal places', function () {
    expect(FormatUptimePercent::make()->handle(100))->toBe('100.00%')
        ->and(FormatUptimePercent::make()->handle(99.996))->toBe('100.00%')
        ->and(FormatUptimePercent::make()->handle(66.6667))->toBe('66.67%')
        ->and(FormatUptimePercent::make()->handle(0))->toBe('0.00%');
});

it('returns null when uptime is missing', function () {
    expect(FormatUptimePercent::make()->handle(null))->toBeNull();
});
