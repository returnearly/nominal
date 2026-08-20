<?php

declare(strict_types=1);

use App\Enums\MonitorType;
use App\Jobs\RunCheckJob;
use App\Models\Monitor;
use Database\Seeders\DemoMonitorSeeder;
use Illuminate\Support\Facades\Queue;

it('creates a demo monitor for each type', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);

    foreach (MonitorType::cases() as $type) {
        $monitor = Monitor::query()->where('type', $type)->where('group', 'demo')->first();

        expect($monitor)->not->toBeNull()
            ->and($monitor?->probes()->where('slug', 'local')->exists())->toBeTrue()
            ->and($monitor?->conditions()->exists())->toBeTrue();
    }
});

it('creates failing monitors for local testing', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);

    expect(Monitor::query()->where('group', 'failing')->orderBy('name')->pluck('name')->all())
        ->toBe([
            'Failing DNS',
            'Failing HTTP status',
            'Failing HTTP unreachable',
            'Failing Ping',
            'Failing TCP',
            'Failing TLS',
        ]);
});

it('does not duplicate demo monitors when seeded twice', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);
    $this->seed(DemoMonitorSeeder::class);

    expect(Monitor::query()->count())->toBe(12);
});

it('queues a first check for demo monitors that have never run', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);

    Queue::assertPushed(RunCheckJob::class, 11);
});
