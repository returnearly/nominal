<?php

declare(strict_types=1);

use App\Enums\MonitorType;
use App\Jobs\RunCheckJob;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\StatusPage;
use Database\Seeders\DemoMonitorSeeder;
use Illuminate\Support\Facades\Queue;

it('creates a demo monitor for each type', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);

    foreach (MonitorType::cases() as $type) {
        $monitor = Monitor::query()->where('type', $type)->tagged('example')->first();

        expect($monitor)->not->toBeNull();

        if ($type === MonitorType::Heartbeat) {
            expect($monitor?->probes()->exists())->toBeFalse()
                ->and($monitor?->conditions()->exists())->toBeFalse();

            continue;
        }

        expect($monitor?->probes()->where('slug', 'local')->exists())->toBeTrue()
            ->and($monitor?->conditions()->exists())->toBeTrue();
    }
});

it('creates failing monitors for local testing', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);

    expect(Monitor::query()->tagged('synthetic')->orderBy('name')->pluck('name')->all())
        ->toBe([
            'Failing DNS',
            'Failing GraphQL',
            'Failing HTTP status',
            'Failing HTTP unreachable',
            'Failing MySQL',
            'Failing Ping',
            'Failing PostgreSQL',
            'Failing Redis',
            'Failing TCP',
            'Failing TLS',
            'Failing UDP',
            'Failing WebSocket',
        ]);

    $failing = Monitor::query()->tagged('synthetic')->first();

    expect($failing?->tags)->toBe(['synthetic'])
        ->and($failing?->description)->toContain('Intentionally broken');
});

it('does not duplicate demo monitors when seeded twice', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);
    $this->seed(DemoMonitorSeeder::class);

    expect(Monitor::query()->count())->toBe(24)
        ->and(MaintenanceWindow::query()->count())->toBe(2)
        ->and(StatusPage::query()->count())->toBe(1);
});

it('covers a share of monitors with past and upcoming maintenance windows', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);

    $monitors = Monitor::query()->orderBy('name')->get();
    $share = max(1, (int) ceil($monitors->count() * 0.3));
    $past = MaintenanceWindow::query()->where('title', 'Completed OS patching')->first();
    $upcoming = MaintenanceWindow::query()->where('title', 'Database failover drill')->first();

    expect($past)->not->toBeNull()
        ->and($past?->phase())->toBe('ended')
        ->and($past?->monitors)->toHaveCount($share)
        ->and($upcoming)->not->toBeNull()
        ->and($upcoming?->phase())->toBe('scheduled')
        ->and($upcoming?->monitors)->toHaveCount($share)
        ->and($past?->monitors->pluck('id')->intersect($upcoming?->monitors->pluck('id') ?? collect())->all())
        ->toBe([]);
});

it('publishes a status page listing every demo monitor', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);

    $page = StatusPage::query()->where('slug', 'demo')->first();
    $monitorIds = Monitor::query()->orderBy('name')->pluck('id');

    expect($page)->not->toBeNull()
        ->and($page?->published)->toBeTrue()
        ->and($page?->name)->toBe('Nominal Status')
        ->and($page?->listings()->orderBy('sort')->pluck('monitor_id')->all())->toBe($monitorIds->all());
});

it('queues a first check for demo monitors that have never run', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);

    Queue::assertPushed(RunCheckJob::class, 23);
});
