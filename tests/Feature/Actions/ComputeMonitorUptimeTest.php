<?php

declare(strict_types=1);

use App\Actions\ComputeMonitorUptime;
use App\Enums\AggregateGranularity;
use App\Models\CheckAggregate;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use App\Uptime\MonitorUptime;
use Illuminate\Support\Carbon;

it('returns null percents when a monitor has no checks', function () {
    $monitor = Monitor::factory()->create();

    $uptime = ComputeMonitorUptime::make()->handle([$monitor->id])->get($monitor->id);

    expect($uptime)->toEqual(MonitorUptime::empty());
});

it('computes 1h and 24h uptime from check results', function () {
    $this->travelTo(Carbon::parse('2026-08-20 15:30:00'));

    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create();

    CheckResult::factory()->count(3)->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => true,
        'checked_at' => now()->subMinutes(10),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => false,
        'checked_at' => now()->subMinutes(5),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => false,
        'checked_at' => now()->subHours(6),
    ]);

    $uptime = ComputeMonitorUptime::make()->handle([$monitor->id])->get($monitor->id);

    expect($uptime->oneHour)->toBe(75.0)
        ->and($uptime->twentyFourHours)->toBe(60.0)
        ->and($uptime->sevenDays)->toBe(60.0)
        ->and($uptime->thirtyDays)->toBe(60.0);
});

it('uses hourly monitor rollups for 7d and 30d uptime', function () {
    $this->travelTo(Carbon::parse('2026-08-20 15:30:00'));

    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create();

    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => true,
        'checked_at' => now()->subMinutes(2),
    ]);

    CheckAggregate::query()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => null,
        'period_start' => now()->subHours(3)->startOfHour(),
        'granularity' => AggregateGranularity::Hour,
        'up_count' => 9,
        'down_count' => 1,
        'avg_latency_ms' => 40,
    ]);
    CheckAggregate::query()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => null,
        'period_start' => now()->subDays(10)->startOfHour(),
        'granularity' => AggregateGranularity::Hour,
        'up_count' => 50,
        'down_count' => 50,
        'avg_latency_ms' => 80,
    ]);
    CheckAggregate::query()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'period_start' => now()->subHours(3)->startOfHour(),
        'granularity' => AggregateGranularity::Hour,
        'up_count' => 0,
        'down_count' => 99,
        'avg_latency_ms' => 900,
    ]);

    $uptime = ComputeMonitorUptime::make()->handle([$monitor->id])->get($monitor->id);

    expect($uptime->oneHour)->toBe(100.0)
        ->and($uptime->twentyFourHours)->toBe(100.0)
        ->and($uptime->sevenDays)->toBe(90.9091)
        ->and($uptime->thirtyDays)->toBe(54.0541);
});
