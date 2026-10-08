<?php

declare(strict_types=1);

use App\Actions\PauseMonitor;
use App\Actions\ResumeMonitor;
use App\Actions\SaveMaintenanceWindow;
use App\Enums\MonitorStatus;
use App\Events\MonitorStatusUpdated;
use App\Models\Monitor;
use Illuminate\Support\Facades\Event;

it('pauses a monitor and broadcasts the status change', function () {
    Event::fake([MonitorStatusUpdated::class]);
    $this->freezeTime();

    $monitor = Monitor::factory()->create([
        'status' => MonitorStatus::Up,
        'last_status_changed_at' => now()->subHour(),
    ]);

    $paused = PauseMonitor::make()->handle($monitor);

    expect($paused->status)->toBe(MonitorStatus::Paused)
        ->and($paused->enabled)->toBeTrue()
        ->and($paused->last_status_changed_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($monitor->fresh()->status)->toBe(MonitorStatus::Paused);

    Event::assertDispatched(
        MonitorStatusUpdated::class,
        fn (MonitorStatusUpdated $event): bool => $event->monitor->is($monitor)
            && $event->previous === MonitorStatus::Up
            && $event->monitor->status === MonitorStatus::Paused,
    );
});

it('does not change a monitor that is already paused', function () {
    Event::fake([MonitorStatusUpdated::class]);
    $this->freezeTime();

    $changedAt = now()->subDay();
    $monitor = Monitor::factory()->create([
        'status' => MonitorStatus::Paused,
        'last_status_changed_at' => $changedAt,
    ]);

    PauseMonitor::make()->handle($monitor);

    expect($monitor->fresh()->last_status_changed_at?->toDateTimeString())->toBe($changedAt->toDateTimeString());

    Event::assertNotDispatched(MonitorStatusUpdated::class);
});

it('stores paused while an active maintenance window still displays as maintenance', function () {
    Event::fake([MonitorStatusUpdated::class]);

    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);

    SaveMaintenanceWindow::make()->handle([
        'title' => 'Database upgrade',
        'monitorIds' => [$monitor->id],
    ]);

    PauseMonitor::make()->handle($monitor->fresh());

    $fresh = $monitor->fresh();

    expect($fresh->status)->toBe(MonitorStatus::Paused)
        ->and($fresh->effectiveStatus())->toBe(MonitorStatus::Maintenance);
});

it('resumes a paused monitor as pending and due now', function () {
    Event::fake([MonitorStatusUpdated::class]);
    $this->freezeTime();

    $monitor = Monitor::factory()->create([
        'status' => MonitorStatus::Paused,
        'next_check_at' => now()->addHour(),
    ]);

    $resumed = ResumeMonitor::make()->handle($monitor);

    expect($resumed->status)->toBe(MonitorStatus::Pending)
        ->and($resumed->next_check_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($resumed->last_status_changed_at?->toDateTimeString())->toBe(now()->toDateTimeString());

    Event::assertDispatched(
        MonitorStatusUpdated::class,
        fn (MonitorStatusUpdated $event): bool => $event->previous === MonitorStatus::Paused
            && $event->monitor->status === MonitorStatus::Pending,
    );
});

it('does not change a monitor that is not paused', function () {
    Event::fake([MonitorStatusUpdated::class]);
    $this->freezeTime();

    $monitor = Monitor::factory()->create([
        'status' => MonitorStatus::Down,
        'next_check_at' => now()->addMinutes(10),
    ]);

    ResumeMonitor::make()->handle($monitor);

    $fresh = $monitor->fresh();

    expect($fresh->status)->toBe(MonitorStatus::Down)
        ->and($fresh->next_check_at?->toDateTimeString())->toBe(now()->addMinutes(10)->toDateTimeString());

    Event::assertNotDispatched(MonitorStatusUpdated::class);
});
