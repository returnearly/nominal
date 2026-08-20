<?php

declare(strict_types=1);

use App\Actions\CheckHeartbeat;
use App\Models\Monitor;

it('fails heartbeat checks when no ping has arrived', function () {
    $monitor = Monitor::factory()->heartbeat()->create();

    $result = CheckHeartbeat::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('Heartbeat not received');
});

it('passes heartbeat checks when a ping arrived inside the interval', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->heartbeat()->create([
        'interval_seconds' => 60,
    ]);
    $monitor->last_heartbeat_at = now()->subSeconds(15);
    $monitor->save();

    $result = CheckHeartbeat::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue();
});

it('fails heartbeat checks when a start signal is not followed by a finish', function () {
    $this->freezeSecond();

    $monitor = Monitor::factory()->heartbeat()->create([
        'interval_seconds' => 60,
    ]);
    $monitor->heartbeat_started_at = now()->subSeconds(60);
    $monitor->save();

    $result = CheckHeartbeat::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('Heartbeat started but not finished')
        ->and($result->latencyMs)->toBe(60000);
});

it('passes heartbeat checks while a started job is still within the interval', function () {
    $this->freezeSecond();

    $monitor = Monitor::factory()->heartbeat()->create([
        'interval_seconds' => 60,
    ]);
    $monitor->heartbeat_started_at = now()->subSeconds(15);
    $monitor->save();

    $result = CheckHeartbeat::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue()
        ->and($result->latencyMs)->toBe(15000);
});

it('gives heartbeat monitors a grace period before the first missed ping', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->heartbeat()->create([
        'interval_seconds' => 90,
    ]);

    expect($monitor->heartbeat_token)->toHaveLength(48)
        ->and($monitor->next_check_at?->toDateTimeString())->toBe(now()->addSeconds(90)->toDateTimeString());
});
