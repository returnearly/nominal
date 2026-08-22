<?php

declare(strict_types=1);

use App\Actions\SaveMonitor;
use App\Enums\MonitorType;
use App\Jobs\RunCheckJob;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Queue::fake();
});

it('queues a check when a monitor is created', function () {
    $probe = Probe::factory()->asDefault()->create(['queue' => 'checks.local']);

    $monitor = SaveMonitor::make()->handle([
        'name' => 'API',
        'type' => MonitorType::Http,
        'target' => 'https://example.com/health',
    ]);

    Queue::assertPushedOn('checks.local', function (RunCheckJob $job) use ($monitor, $probe): bool {
        return $job->monitorId === $monitor->id && $job->probeId === $probe->id;
    });
});

it('queues a check when a monitor is updated', function () {
    $probe = Probe::factory()->create(['queue' => 'checks.local']);
    $monitor = Monitor::factory()->create();
    $monitor->probes()->attach($probe);

    SaveMonitor::make()->handle([
        'name' => 'API renamed',
        'target' => 'https://example.com/v2',
    ], $monitor);

    Queue::assertPushedOn('checks.local', function (RunCheckJob $job) use ($monitor, $probe): bool {
        return $job->monitorId === $monitor->id && $job->probeId === $probe->id;
    });
});

it('does not queue a check for heartbeat or disabled monitors', function () {
    Probe::factory()->asDefault()->create();

    SaveMonitor::make()->handle([
        'name' => 'Nightly backup',
        'type' => MonitorType::Heartbeat,
        'target' => 'backup-job',
    ]);

    SaveMonitor::make()->handle([
        'name' => 'Paused API',
        'type' => MonitorType::Http,
        'target' => 'https://example.com/health',
        'enabled' => false,
    ]);

    Queue::assertNothingPushed();
});

it('attaches enabled default probes when none are specified', function () {
    $default = Probe::factory()->asDefault()->create();
    Probe::factory()->create(['is_default' => false]);
    Probe::factory()->asDefault()->create(['enabled' => false]);

    $monitor = SaveMonitor::make()->handle([
        'name' => 'API',
        'type' => MonitorType::Http,
        'target' => 'https://example.com/health',
    ]);

    expect($monitor->probes()->pluck('id')->all())->toBe([$default->id]);
});

it('does not replace existing probes when they are omitted on update', function () {
    $original = Probe::factory()->create();
    Probe::factory()->asDefault()->create();

    $monitor = Monitor::factory()->create();
    $monitor->probes()->attach($original);

    $updated = SaveMonitor::make()->handle([
        'name' => 'API renamed',
    ], $monitor);

    expect($updated->probes()->pluck('id')->all())->toBe([$original->id]);
});

it('uses the supplied probe ids instead of defaults', function () {
    Probe::factory()->asDefault()->create();
    $chosen = Probe::factory()->create(['is_default' => false]);

    $monitor = SaveMonitor::make()->handle([
        'name' => 'API',
        'type' => MonitorType::Http,
        'target' => 'https://example.com/health',
        'probeIds' => [$chosen->id],
    ]);

    expect($monitor->probes()->pluck('id')->all())->toBe([$chosen->id]);
});

it('rejects domain expiration checks with an interval under 5 minutes', function () {
    Probe::factory()->asDefault()->create();

    expect(fn () => SaveMonitor::make()->handle([
        'name' => 'example.com',
        'type' => MonitorType::Http,
        'target' => 'https://example.com',
        'intervalSeconds' => 60,
        'conditions' => ['[DOMAIN_EXPIRATION] > 720h'],
    ]))->toThrow(ValidationException::class, '[DOMAIN_EXPIRATION]');
});

it('saves a domain expiration monitor when the interval is at least 5 minutes', function () {
    Probe::factory()->asDefault()->create();

    $monitor = SaveMonitor::make()->handle([
        'name' => 'example.com',
        'type' => MonitorType::Http,
        'target' => 'https://example.com',
        'intervalSeconds' => 3600,
        'conditions' => ['[DOMAIN_EXPIRATION] > 720h'],
    ]);

    expect($monitor->interval_seconds)->toBe(3600)
        ->and($monitor->conditions()->pluck('expression')->all())->toBe(['[DOMAIN_EXPIRATION] > 720h']);
});
