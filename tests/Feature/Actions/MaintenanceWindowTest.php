<?php

declare(strict_types=1);

use App\Actions\EndMaintenanceWindow;
use App\Actions\EndMonitorMaintenance;
use App\Actions\SaveMaintenanceWindow;
use App\Actions\StartMonitorMaintenance;
use App\Enums\MonitorStatus;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use Illuminate\Validation\ValidationException;

it('marks selected monitors as under maintenance', function () {
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Down]);
    $other = Monitor::factory()->create(['status' => MonitorStatus::Down]);

    $window = SaveMaintenanceWindow::make()->handle([
        'title' => 'Database upgrade',
        'message' => 'Upgrading Postgres.',
        'monitorIds' => [$monitor->id],
    ]);

    expect($window->phase())->toBe('active')
        ->and($monitor->fresh()->isUnderMaintenance())->toBeTrue()
        ->and($monitor->fresh()->effectiveStatus())->toBe(MonitorStatus::Maintenance)
        ->and($other->fresh()->isUnderMaintenance())->toBeFalse()
        ->and($other->fresh()->effectiveStatus())->toBe(MonitorStatus::Down);
});

it('applies a window to every monitor', function () {
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);

    SaveMaintenanceWindow::make()->handle([
        'title' => 'Site-wide maintenance',
        'appliesToAll' => true,
    ]);

    expect($monitor->fresh()->isUnderMaintenance())->toBeTrue()
        ->and($monitor->fresh()->effectiveStatus())->toBe(MonitorStatus::Maintenance);
});

it('ignores scheduled, ended, and cancelled windows', function () {
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);

    MaintenanceWindow::factory()->scheduled()->withMonitors([$monitor])->create();
    MaintenanceWindow::factory()->ended()->withMonitors([$monitor])->create();
    MaintenanceWindow::factory()->cancelled()->withMonitors([$monitor])->create();

    expect($monitor->fresh()->isUnderMaintenance())->toBeFalse()
        ->and($monitor->fresh()->effectiveStatus())->toBe(MonitorStatus::Up);
});

it('starts and ends maintenance on a single monitor', function () {
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Down]);
    $other = Monitor::factory()->create();

    $window = MaintenanceWindow::factory()->withMonitors([$monitor, $other])->create();

    StartMonitorMaintenance::make()->handle($monitor, [
        'title' => 'Patching',
        'message' => 'Restarting the API.',
    ]);

    EndMonitorMaintenance::make()->handle($monitor);

    expect($monitor->fresh()->isUnderMaintenance())->toBeFalse()
        ->and($other->fresh()->isUnderMaintenance())->toBeTrue()
        ->and($window->fresh()->monitors()->pluck('id')->all())->toBe([$other->id]);
});

it('ends an active window and cancels a scheduled one', function () {
    $active = MaintenanceWindow::factory()->forAll()->create();
    $scheduled = MaintenanceWindow::factory()->forAll()->scheduled()->create();

    EndMaintenanceWindow::make()->handle($active);
    EndMaintenanceWindow::make()->handle($scheduled);

    expect($active->fresh()->phase())->toBe('ended')
        ->and($active->fresh()->ends_at?->lte(now()))->toBeTrue()
        ->and($scheduled->fresh()->phase())->toBe('cancelled')
        ->and($scheduled->fresh()->cancelled_at)->not->toBeNull();
});

it('requires a title and at least one monitor', function () {
    SaveMaintenanceWindow::make()->handle([
        'title' => '',
        'appliesToAll' => true,
    ]);
})->throws(ValidationException::class);

it('requires monitors when the window is not global', function () {
    SaveMaintenanceWindow::make()->handle([
        'title' => 'Partial',
        'appliesToAll' => false,
        'monitorIds' => [],
    ]);
})->throws(ValidationException::class);
