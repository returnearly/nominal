<?php

declare(strict_types=1);

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;

it('records a successful check when a heartbeat is received', function () {
    $monitor = Monitor::factory()->heartbeat()->create();

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token.'?latency=42')
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);

    $monitor->refresh();

    expect($monitor->status)->toBe(MonitorStatus::Up)
        ->and($monitor->last_heartbeat_at)->not->toBeNull()
        ->and($monitor->heartbeat_started_at)->toBeNull()
        ->and($monitor->checkResults()->first()?->latency_ms)->toBe(42)
        ->and($monitor->checkResults()->first()?->probe_id)->toBeNull();
});

it('accepts POST heartbeats', function () {
    $monitor = Monitor::factory()->heartbeat()->create();

    $this->postJson('/api/heartbeat/'.$monitor->heartbeat_token, ['ping' => 12])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('rejects unknown heartbeat tokens', function () {
    $this->getJson('/api/heartbeat/notarealtoken')->assertNotFound();
});

it('records a start signal without creating a check result', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->heartbeat()->create([
        'interval_seconds' => 60,
    ]);

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token.'/start')
        ->assertOk()
        ->assertJson(['ok' => true, 'started' => true])
        ->assertJsonMissing(['success']);

    $monitor->refresh();

    expect($monitor->heartbeat_started_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($monitor->last_heartbeat_at)->toBeNull()
        ->and($monitor->next_check_at?->toDateTimeString())->toBe(now()->addSeconds(60)->toDateTimeString())
        ->and($monitor->checkResults()->count())->toBe(0);
});

it('measures job duration between start and finish signals', function () {
    $this->freezeSecond();

    $monitor = Monitor::factory()->heartbeat()->create();

    $this->postJson('/api/heartbeat/'.$monitor->heartbeat_token.'/start')->assertOk();

    $this->travel(5)->seconds();

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token.'/finish')
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);

    $monitor->refresh();

    expect($monitor->status)->toBe(MonitorStatus::Up)
        ->and($monitor->heartbeat_started_at)->toBeNull()
        ->and($monitor->last_heartbeat_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($monitor->checkResults()->first()?->success)->toBeTrue()
        ->and($monitor->checkResults()->first()?->latency_ms)->toBe(5000);
});

it('records an error signal as a failed check with the elapsed duration', function () {
    $this->freezeSecond();

    $monitor = Monitor::factory()->heartbeat()->create();

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token.'/start')->assertOk();

    $this->travel(2)->seconds();

    $this->postJson('/api/heartbeat/'.$monitor->heartbeat_token.'/error')
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => false]);

    $monitor->refresh();

    expect($monitor->status)->toBe(MonitorStatus::Down)
        ->and($monitor->heartbeat_started_at)->toBeNull()
        ->and($monitor->checkResults()->first()?->success)->toBeFalse()
        ->and($monitor->checkResults()->first()?->latency_ms)->toBe(2000)
        ->and($monitor->checkResults()->first()?->message)->toBe('Heartbeat reported an error');
});

it('rejects unknown heartbeat signals', function () {
    $monitor = Monitor::factory()->heartbeat()->create();

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token.'/fail')->assertNotFound();
});

it('treats finish without a start as a regular success ping', function () {
    $monitor = Monitor::factory()->heartbeat()->create();

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token.'/finish?latency=18')
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);

    expect($monitor->fresh()->checkResults()->first()?->latency_ms)->toBe(18);
});

it('still accepts a heartbeat when broadcasting to reverb fails', function () {
    Broadcast::extend('throwing', function () {
        return new class extends NullBroadcaster
        {
            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new BroadcastException(
                    'Pusher error: Failed to connect to localhost port 55411',
                );
            }
        };
    });

    config([
        'broadcasting.default' => 'throwing',
        'broadcasting.connections.throwing' => ['driver' => 'throwing'],
    ]);

    $monitor = Monitor::factory()->heartbeat()->create();

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token)
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);

    expect($monitor->fresh()->status)->toBe(MonitorStatus::Up)
        ->and($monitor->checkResults()->count())->toBe(1);
});
