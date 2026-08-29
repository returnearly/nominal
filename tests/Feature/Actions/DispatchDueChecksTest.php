<?php

declare(strict_types=1);

use App\Actions\DispatchDueChecks;
use App\Enums\MonitorStatus;
use App\Jobs\RunCheckJob;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Support\Facades\Queue;

it('sets next_check_at to now when a monitor is created', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->create();

    expect($monitor->next_check_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('dispatches only monitors whose next_check_at is due', function () {
    Queue::fake();
    $this->freezeTime();

    $probe = Probe::factory()->create(['queue' => 'checks.local']);
    $due = Monitor::factory()->create(['next_check_at' => now()->subSecond()]);
    $later = Monitor::factory()->create(['next_check_at' => now()->addMinute()]);
    $paused = Monitor::factory()->create([
        'status' => MonitorStatus::Paused,
        'next_check_at' => now()->subSecond(),
    ]);

    $due->probes()->attach($probe);
    $later->probes()->attach($probe);
    $paused->probes()->attach($probe);

    expect(DispatchDueChecks::make()->handle())->toBe(1);

    Queue::assertPushed(fn (RunCheckJob $job): bool => $job->monitorId === $due->id);
    Queue::assertNotPushed(fn (RunCheckJob $job): bool => $job->monitorId === $later->id);
    Queue::assertNotPushed(fn (RunCheckJob $job): bool => $job->monitorId === $paused->id);
});

it('does not dispatch an in-progress heartbeat until the job overruns the interval', function () {
    Queue::fake();
    $this->freezeTime();

    $running = Monitor::factory()->heartbeat()->create([
        'interval_seconds' => 60,
        'next_check_at' => now()->subSecond(),
    ]);
    $running->heartbeat_started_at = now()->subSeconds(10);
    $running->save();

    $hung = Monitor::factory()->heartbeat()->create([
        'interval_seconds' => 60,
        'next_check_at' => now()->subSecond(),
    ]);
    $hung->heartbeat_started_at = now()->subSeconds(60);
    $hung->save();

    expect(DispatchDueChecks::make()->handle())->toBe(1);

    Queue::assertNotPushed(fn (RunCheckJob $job): bool => $job->monitorId === $running->id);
    Queue::assertPushed(fn (RunCheckJob $job): bool => $job->monitorId === $hung->id);

    expect($running->fresh()->next_check_at?->toDateTimeString())
        ->toBe(now()->subSecond()->toDateTimeString());
});

it('pushes next_check_at forward when due checks are dispatched', function () {
    Queue::fake();
    $this->freezeTime();

    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->create([
        'interval_seconds' => 90,
        'next_check_at' => now()->subSecond(),
    ]);
    $monitor->probes()->attach($probe);

    DispatchDueChecks::make()->handle();

    expect($monitor->fresh()->next_check_at?->toDateTimeString())
        ->toBe(now()->copy()->addSeconds(90)->toDateTimeString());
});
