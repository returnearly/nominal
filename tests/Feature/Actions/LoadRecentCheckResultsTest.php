<?php

declare(strict_types=1);

use App\Actions\LoadRecentCheckResults;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;

it('returns the latest checks per monitor, oldest first', function () {
    $this->freezeTime();

    $probe = Probe::factory()->create();
    $alpha = Monitor::factory()->create();
    $beta = Monitor::factory()->create();

    foreach (range(1, 22) as $i) {
        CheckResult::factory()->create([
            'monitor_id' => $alpha->id,
            'probe_id' => $probe->id,
            'checked_at' => now()->subMinutes(22 - $i),
            'latency_ms' => $i,
        ]);
    }

    CheckResult::factory()->create([
        'monitor_id' => $beta->id,
        'probe_id' => $probe->id,
        'checked_at' => now()->subMinute(),
        'success' => false,
    ]);

    $heartbeats = LoadRecentCheckResults::make()->handle([$alpha->id, $beta->id], 20);

    expect($heartbeats[$alpha->id])->toHaveCount(20)
        ->and($heartbeats[$alpha->id]->first()->latency_ms)->toBe(3)
        ->and($heartbeats[$alpha->id]->last()->latency_ms)->toBe(22)
        ->and($heartbeats[$beta->id])->toHaveCount(1)
        ->and($heartbeats[$beta->id]->first()->success)->toBeFalse();
});

it('returns an empty collection for monitors with no checks', function () {
    $monitor = Monitor::factory()->create();

    $heartbeats = LoadRecentCheckResults::make()->handle([$monitor->id]);

    expect($heartbeats[$monitor->id])->toHaveCount(0);
});

it('keeps the newest checks for each monitor, oldest first', function () {
    $this->freezeTime();

    $probe = Probe::factory()->create(['name' => 'Edge']);
    $over = Monitor::factory()->create();
    $under = Monitor::factory()->create();
    $empty = Monitor::factory()->create();

    foreach (range(1, 5) as $i) {
        CheckResult::factory()->create([
            'monitor_id' => $over->id,
            'probe_id' => $probe->id,
            'checked_at' => now()->subMinutes(5 - $i),
            'latency_ms' => $i,
        ]);
    }

    CheckResult::factory()->create([
        'monitor_id' => $under->id,
        'probe_id' => $probe->id,
        'checked_at' => now()->subMinute(),
        'success' => false,
        'latency_ms' => 9,
    ]);

    $heartbeats = LoadRecentCheckResults::make()->handle([
        $over->id,
        $under->id,
        $empty->id,
    ], 3);

    expect($heartbeats->keys()->all())->toBe([$over->id, $under->id, $empty->id])
        ->and($heartbeats[$over->id])->toHaveCount(3)
        ->and($heartbeats[$over->id]->pluck('latency_ms')->all())->toBe([3, 4, 5])
        ->and($heartbeats[$under->id])->toHaveCount(1)
        ->and($heartbeats[$under->id]->first()->success)->toBeFalse()
        ->and($heartbeats[$under->id]->first()->probe?->name)->toBe('Edge')
        ->and($heartbeats[$empty->id])->toHaveCount(0);
});
