<?php

declare(strict_types=1);

use App\Checking\HeartbeatChecker;
use App\Models\Monitor;

it('fails heartbeat checks when no ping has arrived', function () {
    $monitor = Monitor::factory()->heartbeat()->withDefaultConditions()->create();
    $monitor->load('conditions');

    $result = app(HeartbeatChecker::class)->check($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('Heartbeat not received');
});

it('passes heartbeat checks when a ping arrived inside the interval', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->heartbeat()->withDefaultConditions()->create([
        'interval_seconds' => 60,
    ]);
    $monitor->last_heartbeat_at = now()->subSeconds(15);
    $monitor->save();
    $monitor->load('conditions');

    $result = app(HeartbeatChecker::class)->check($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue();
});

it('gives heartbeat monitors a grace period before the first missed ping', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->heartbeat()->create([
        'interval_seconds' => 90,
    ]);

    expect($monitor->heartbeat_token)->toHaveLength(48)
        ->and($monitor->next_check_at?->toDateTimeString())->toBe(now()->addSeconds(90)->toDateTimeString());
});
