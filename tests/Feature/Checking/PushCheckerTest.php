<?php

declare(strict_types=1);

use App\Checking\PushChecker;
use App\Models\Monitor;

it('fails push checks when no heartbeat has arrived', function () {
    $monitor = Monitor::factory()->push()->withDefaultConditions()->create();
    $monitor->load('conditions');

    $result = app(PushChecker::class)->check($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('Heartbeat not received');
});

it('passes push checks when a heartbeat arrived inside the interval', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->push()->withDefaultConditions()->create([
        'interval_seconds' => 60,
    ]);
    $monitor->last_heartbeat_at = now()->subSeconds(15);
    $monitor->save();
    $monitor->load('conditions');

    $result = app(PushChecker::class)->check($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue();
});

it('gives push monitors a grace period before the first missed heartbeat', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->push()->create([
        'interval_seconds' => 90,
    ]);

    expect($monitor->push_token)->toHaveLength(48)
        ->and($monitor->next_check_at?->toDateTimeString())->toBe(now()->addSeconds(90)->toDateTimeString());
});
