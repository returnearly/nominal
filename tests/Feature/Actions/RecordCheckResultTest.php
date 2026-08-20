<?php

declare(strict_types=1);

use App\Actions\RecordCheckResult;
use App\Checking\ProbeResult;
use App\Enums\MonitorStatus;
use App\Events\CheckCompleted;
use App\Events\MonitorStatusUpdated;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('records a successful check and marks the monitor up', function () {
    Event::fake([CheckCompleted::class, MonitorStatusUpdated::class]);

    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Pending]);
    $probe = Probe::factory()->create();

    $result = new ProbeResult(
        success: true,
        connected: true,
        latencyMs: 42,
        httpStatus: 200,
        resolvedIp: '1.2.3.4',
        certificateExpiresAt: null,
        message: null,
        conditionResults: [],
    );

    $stored = RecordCheckResult::make()->handle($monitor, $probe, $result);

    expect($stored->success)->toBeTrue()
        ->and(Str::isUuid($stored->id, 7))->toBeTrue()
        ->and($monitor->fresh()->status)->toBe(MonitorStatus::Up)
        ->and($monitor->consecutive_successes)->toBe(1)
        ->and($monitor->consecutive_failures)->toBe(0);

    Event::assertDispatched(CheckCompleted::class);
    Event::assertDispatched(MonitorStatusUpdated::class);
});

it('records a failed check and marks the monitor down', function () {
    Event::fake([CheckCompleted::class, MonitorStatusUpdated::class]);

    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);
    $probe = Probe::factory()->create();

    RecordCheckResult::make()->handle($monitor, $probe, new ProbeResult(
        success: false,
        connected: false,
        latencyMs: 5,
        httpStatus: null,
        resolvedIp: null,
        certificateExpiresAt: null,
        message: 'down',
        conditionResults: [],
    ));

    expect($monitor->fresh()->status)->toBe(MonitorStatus::Down)
        ->and($monitor->consecutive_failures)->toBe(1)
        ->and($monitor->consecutive_successes)->toBe(0);
});

it('schedules the next check when a result is recorded', function () {
    Event::fake([CheckCompleted::class, MonitorStatusUpdated::class]);
    $this->freezeTime();

    $monitor = Monitor::factory()->create(['interval_seconds' => 60]);
    $probe = Probe::factory()->create();

    RecordCheckResult::make()->handle($monitor, $probe, new ProbeResult(
        success: true,
        connected: true,
        latencyMs: 10,
        httpStatus: 200,
        resolvedIp: '1.2.3.4',
        certificateExpiresAt: null,
        message: null,
        conditionResults: [],
    ));

    expect($monitor->fresh()->next_check_at?->toDateTimeString())
        ->toBe(now()->copy()->addSeconds(60)->toDateTimeString());
});

it('records heartbeat results without a probe', function () {
    Event::fake([CheckCompleted::class, MonitorStatusUpdated::class]);

    $monitor = Monitor::factory()->heartbeat()->create(['status' => MonitorStatus::Pending]);

    $stored = RecordCheckResult::make()->handle($monitor, null, new ProbeResult(
        success: true,
        connected: true,
        latencyMs: 12,
        httpStatus: null,
        resolvedIp: null,
        certificateExpiresAt: null,
        message: null,
        conditionResults: [],
    ));

    expect($stored->probe_id)->toBeNull()
        ->and($monitor->fresh()->status)->toBe(MonitorStatus::Up);
});
