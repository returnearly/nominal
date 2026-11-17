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

it('ignores heartbeats while the monitor is paused', function () {
    $monitor = Monitor::factory()->heartbeat()->create([
        'status' => MonitorStatus::Paused,
    ]);

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token)
        ->assertOk()
        ->assertJson(['ok' => true, 'paused' => true]);

    $this->postJson('/api/heartbeat/'.$monitor->heartbeat_token.'/start')
        ->assertOk()
        ->assertJson(['ok' => true, 'paused' => true]);

    expect($monitor->fresh()->status)->toBe(MonitorStatus::Paused)
        ->and($monitor->fresh()->heartbeat_started_at)->toBeNull()
        ->and($monitor->checkResults()->count())->toBe(0);
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

it('rejects a slug url until slug urls are enabled', function () {
    $monitor = Monitor::factory()->heartbeat()->create([
        'heartbeat_slug' => 'nightly-backup',
        'heartbeat_slug_enabled' => false,
    ]);

    $this->getJson('/api/heartbeat/nightly-backup')->assertNotFound();

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token)
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);
});

it('accepts a slug url when slug urls are enabled', function () {
    $monitor = Monitor::factory()->heartbeat()->create([
        'heartbeat_slug' => 'nightly-backup',
        'heartbeat_slug_enabled' => true,
    ]);

    $this->postJson('/api/heartbeat/Nightly-Backup', ['ping' => 9])
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);

    $this->getJson('/api/heartbeat/nightly-backup/start')->assertOk()->assertJson(['started' => true]);
    $this->postJson('/api/heartbeat/nightly-backup/finish', ['latency' => 15])
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);
    $this->postJson('/api/heartbeat/nightly-backup/error')->assertOk();

    $monitor->refresh();

    expect($monitor->status)->toBe(MonitorStatus::Down)
        ->and($monitor->heartbeatUrl())->toEndWith('/api/heartbeat/nightly-backup')
        ->and($monitor->heartbeatStartUrl())->toEndWith('/api/heartbeat/nightly-backup/start')
        ->and($monitor->heartbeatTokenUrl())->toEndWith('/api/heartbeat/'.$monitor->heartbeat_token);
});

it('keeps the token url working when a slug url is enabled', function () {
    $monitor = Monitor::factory()->heartbeat()->create([
        'heartbeat_slug' => 'nightly-backup',
        'heartbeat_slug_enabled' => true,
    ]);

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token.'?latency=4')
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);
});

it('rejects an unknown or disabled slug url', function () {
    $monitor = Monitor::factory()->heartbeat()->create([
        'heartbeat_slug' => 'nightly-backup',
        'heartbeat_slug_enabled' => true,
        'enabled' => false,
    ]);

    $this->getJson('/api/heartbeat/nightly-backup')->assertNotFound();
    $this->getJson('/api/heartbeat/missing-job')->assertNotFound();
    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token)->assertNotFound();
});
