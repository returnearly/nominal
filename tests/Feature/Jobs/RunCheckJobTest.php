<?php

declare(strict_types=1);

use App\Actions\CheckMonitor;
use App\Actions\RecordCheckResult;
use App\Enums\MonitorStatus;
use App\Jobs\RunCheckJob;
use App\Models\Monitor;

it('does not record a check while the monitor is paused', function () {
    $monitor = Monitor::factory()->heartbeat()->create([
        'status' => MonitorStatus::Paused,
    ]);

    (new RunCheckJob($monitor->id))->handle(
        app(CheckMonitor::class),
        app(RecordCheckResult::class),
    );

    expect($monitor->fresh()->status)->toBe(MonitorStatus::Paused)
        ->and($monitor->checkResults()->count())->toBe(0);
});
