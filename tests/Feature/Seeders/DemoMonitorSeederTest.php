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
        $monitor = Monitor::query()->where('type', $type)->first();

        expect($monitor)->not->toBeNull()
            ->and($monitor?->probes()->where('slug', 'local')->exists())->toBeTrue()
            ->and($monitor?->conditions()->exists())->toBeTrue();
    }
});

it('does not duplicate demo monitors when seeded twice', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);
    $this->seed(DemoMonitorSeeder::class);

    expect(Monitor::query()->count())->toBe(count(MonitorType::cases()));
});

it('queues a first check for demo monitors that have never run', function () {
    Queue::fake();

    $this->seed(DemoMonitorSeeder::class);

    Queue::assertPushed(RunCheckJob::class, count(MonitorType::cases()));
});
