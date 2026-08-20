<?php

declare(strict_types=1);

use App\Enums\MonitorStatus;
use App\Models\Monitor;

it('records a successful check when a heartbeat is received', function () {
    $monitor = Monitor::factory()->heartbeat()->create();

    $this->getJson('/api/heartbeat/'.$monitor->heartbeat_token.'?latency=42')
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);

    $monitor->refresh();

    expect($monitor->status)->toBe(MonitorStatus::Up)
        ->and($monitor->last_heartbeat_at)->not->toBeNull()
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
