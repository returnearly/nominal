<?php

declare(strict_types=1);

use App\Enums\MonitorStatus;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;

it('creates, updates, ends, and deletes a maintenance window', function () {
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Down]);

    $created = graphql('
        mutation ($input: CreateMaintenanceWindowInput!) {
            createMaintenanceWindow(input: $input) {
                id
                title
                message
                applies_to_all
                phase
                monitors { id }
            }
        }
    ', [
        'input' => [
            'title' => 'Database upgrade',
            'message' => 'Upgrading Postgres.',
            'monitorIds' => [$monitor->id],
        ],
    ])->assertSuccessful()
        ->json('data.createMaintenanceWindow');

    expect($created['title'])->toBe('Database upgrade')
        ->and($created['applies_to_all'])->toBeFalse()
        ->and($created['phase'])->toBe('active')
        ->and($created['monitors'][0]['id'])->toBe($monitor->id);

    $status = graphql('
        query ($id: ID!) {
            monitor(id: $id) {
                status
                activeMaintenanceWindow { title }
            }
        }
    ', ['id' => $monitor->id])->json('data.monitor');

    expect($status['status'])->toBe('Maintenance')
        ->and($status['activeMaintenanceWindow']['title'])->toBe('Database upgrade');

    $updated = graphql('
        mutation ($id: ID!, $input: UpdateMaintenanceWindowInput!) {
            updateMaintenanceWindow(id: $id, input: $input) {
                title
                applies_to_all
            }
        }
    ', [
        'id' => $created['id'],
        'input' => [
            'title' => 'Cluster upgrade',
            'appliesToAll' => true,
        ],
    ])->json('data.updateMaintenanceWindow');

    expect($updated['title'])->toBe('Cluster upgrade')
        ->and($updated['applies_to_all'])->toBeTrue();

    $ended = graphql('
        mutation ($id: ID!) {
            endMaintenanceWindow(id: $id) { phase }
        }
    ', ['id' => $created['id']])->json('data.endMaintenanceWindow.phase');

    expect($ended)->toBe('ended');

    $deleted = graphql('
        mutation ($id: ID!) {
            deleteMaintenanceWindow(id: $id)
        }
    ', ['id' => $created['id']])->json('data.deleteMaintenanceWindow');

    expect($deleted)->toBeTrue()
        ->and(MaintenanceWindow::query()->find($created['id']))->toBeNull();
});

it('starts and ends maintenance on a monitor', function () {
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Down]);

    $started = graphql('
        mutation ($monitorId: ID!) {
            startMonitorMaintenance(monitorId: $monitorId, title: "Patching", message: "Restarting the API.") {
                title
                phase
                monitors { id }
            }
        }
    ', ['monitorId' => $monitor->id])->assertSuccessful()
        ->json('data.startMonitorMaintenance');

    expect($started['title'])->toBe('Patching')
        ->and($started['phase'])->toBe('active')
        ->and($started['monitors'][0]['id'])->toBe($monitor->id);

    $ended = graphql('
        mutation ($monitorId: ID!) {
            endMonitorMaintenance(monitorId: $monitorId) { status }
        }
    ', ['monitorId' => $monitor->id])->json('data.endMonitorMaintenance.status');

    expect($ended)->toBe('Down');
});

it('schedules a future window without changing current status', function () {
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);
    $startsAt = now()->addHour()->format('Y-m-d H:i:s');
    $endsAt = now()->addHours(2)->format('Y-m-d H:i:s');

    $created = graphql('
        mutation ($input: CreateMaintenanceWindowInput!) {
            createMaintenanceWindow(input: $input) {
                phase
            }
        }
    ', [
        'input' => [
            'title' => 'Overnight upgrade',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'appliesToAll' => true,
        ],
    ])->json('data.createMaintenanceWindow');

    expect($created['phase'])->toBe('scheduled');

    $status = graphql('
        query ($id: ID!) { monitor(id: $id) { status } }
    ', ['id' => $monitor->id])->json('data.monitor.status');

    expect($status)->toBe('Up');
});
