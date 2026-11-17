<?php

declare(strict_types=1);

use App\Enums\MonitorStatus;
use App\Models\Monitor;

it('pauses and resumes a monitor', function () {
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);

    $paused = graphql('
        mutation ($monitorId: ID!) {
            pauseMonitor(monitorId: $monitorId) {
                status
                enabled
            }
        }
    ', ['monitorId' => $monitor->id])->assertSuccessful()
        ->json('data.pauseMonitor');

    expect($paused['status'])->toBe('Paused')
        ->and($paused['enabled'])->toBeTrue()
        ->and($monitor->fresh()->status)->toBe(MonitorStatus::Paused);

    $resumed = graphql('
        mutation ($monitorId: ID!) {
            resumeMonitor(monitorId: $monitorId) {
                status
            }
        }
    ', ['monitorId' => $monitor->id])->assertSuccessful()
        ->json('data.resumeMonitor');

    expect($resumed['status'])->toBe('Pending')
        ->and($monitor->fresh()->status)->toBe(MonitorStatus::Pending);
});
