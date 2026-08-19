<?php

declare(strict_types=1);

use App\Actions\BuildUptimeHeatmap;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;

it('builds a 7-day hourly heatmap from check results', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create();
    $hour = now()->startOfHour()->subHours(3);

    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'checked_at' => $hour->copy()->addMinutes(5),
        'success' => true,
        'latency_ms' => 20,
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'checked_at' => $hour->copy()->addMinutes(15),
        'success' => false,
        'latency_ms' => 80,
    ]);

    $cells = BuildUptimeHeatmap::make()->handle($monitor);

    expect($cells)->toHaveCount(7 * 24);

    $cell = collect($cells)->first(
        fn (array $cell): bool => $cell['start']->equalTo($hour),
    );

    expect($cell)->not->toBeNull()
        ->and($cell['up'])->toBe(1)
        ->and($cell['down'])->toBe(1)
        ->and($cell['avg_latency_ms'])->toBe(50);
});
