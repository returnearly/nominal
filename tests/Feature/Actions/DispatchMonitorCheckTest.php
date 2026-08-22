<?php

declare(strict_types=1);

use App\Actions\DispatchMonitorCheck;
use App\Jobs\RunCheckJob;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Support\Facades\Queue;

it('queues a check on each enabled probe immediately', function () {
    Queue::fake();

    $monitor = Monitor::factory()->create();
    $local = Probe::factory()->create(['queue' => 'checks.local']);
    $remote = Probe::factory()->create(['queue' => 'checks.us-east']);
    $disabled = Probe::factory()->create(['enabled' => false, 'queue' => 'checks.disabled']);
    $monitor->probes()->attach([$local->id, $remote->id, $disabled->id]);

    $dispatched = DispatchMonitorCheck::make()->handle($monitor);

    expect($dispatched)->toBe(2);

    Queue::assertPushed(RunCheckJob::class, 2);
    Queue::assertPushedOn('checks.local', RunCheckJob::class);
    Queue::assertPushedOn('checks.us-east', RunCheckJob::class);
    Queue::assertNotPushed(fn (RunCheckJob $job): bool => $job->probeId === $disabled->id);
});

it('does not queue checks when no enabled probes are assigned', function () {
    Queue::fake();

    $monitor = Monitor::factory()->create();

    expect(DispatchMonitorCheck::make()->handle($monitor))->toBe(0);

    Queue::assertNothingPushed();
});

it('queues heartbeat checks without a probe', function () {
    Queue::fake();

    $monitor = Monitor::factory()->heartbeat()->create();

    expect(DispatchMonitorCheck::make()->handle($monitor))->toBe(1);

    Queue::assertPushed(function (RunCheckJob $job) use ($monitor): bool {
        return $job->monitorId === $monitor->id && $job->probeId === null;
    });
});

it('queues a check after saving an enabled outbound monitor', function () {
    Queue::fake();

    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create(['queue' => 'checks.local']);
    $monitor->probes()->attach($probe);

    expect(DispatchMonitorCheck::make()->forSaved($monitor))->toBe(1);

    Queue::assertPushedOn('checks.local', function (RunCheckJob $job) use ($monitor, $probe): bool {
        return $job->monitorId === $monitor->id && $job->probeId === $probe->id;
    });
});

it('does not queue a check after saving a heartbeat or disabled monitor', function () {
    Queue::fake();

    $heartbeat = Monitor::factory()->heartbeat()->create();
    $disabled = Monitor::factory()->disabled()->create();
    $probe = Probe::factory()->create();
    $disabled->probes()->attach($probe);

    expect(DispatchMonitorCheck::make()->forSaved($heartbeat))->toBe(0)
        ->and(DispatchMonitorCheck::make()->forSaved($disabled))->toBe(0);

    Queue::assertNothingPushed();
});
