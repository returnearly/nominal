<?php

declare(strict_types=1);

use App\Actions\LoadLatencyTrend;
use App\Enums\AggregateGranularity;
use App\Models\CheckAggregate;
use App\Models\Monitor;

it('returns hourly average latency for the last 24 hours', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->create();

    CheckAggregate::query()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => null,
        'period_start' => now()->subHours(2)->startOfHour(),
        'granularity' => AggregateGranularity::Hour,
        'up_count' => 10,
        'down_count' => 0,
        'avg_latency_ms' => 42,
    ]);
    CheckAggregate::query()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => null,
        'period_start' => now()->subHour()->startOfHour(),
        'granularity' => AggregateGranularity::Hour,
        'up_count' => 8,
        'down_count' => 2,
        'avg_latency_ms' => 80,
    ]);

    $points = LoadLatencyTrend::make()->handle($monitor);

    expect($points)->toHaveCount(2)
        ->and($points->first()->latency_ms)->toBe(42)
        ->and($points->last()->latency_ms)->toBe(80)
        ->and($points->last()->success)->toBeFalse();
});

it('returns no points when hourly latency has not been rolled up', function () {
    $monitor = Monitor::factory()->create();

    expect(LoadLatencyTrend::make()->handle($monitor))->toHaveCount(0);
});
