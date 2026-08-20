<?php

declare(strict_types=1);

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\Probe;

it('records a successful check when a push heartbeat is received', function () {
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->push()->withDefaultConditions()->create();
    $monitor->probes()->attach($probe);

    $this->getJson('/api/push/'.$monitor->push_token.'?latency=42')
        ->assertOk()
        ->assertJson(['ok' => true, 'success' => true]);

    $monitor->refresh();

    expect($monitor->status)->toBe(MonitorStatus::Up)
        ->and($monitor->last_heartbeat_at)->not->toBeNull()
        ->and($monitor->checkResults()->first()?->latency_ms)->toBe(42);
});

it('accepts POST heartbeats', function () {
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->push()->withDefaultConditions()->create();
    $monitor->probes()->attach($probe);

    $this->postJson('/api/push/'.$monitor->push_token, ['ping' => 12])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('rejects unknown push tokens', function () {
    $this->getJson('/api/push/notarealtoken')->assertNotFound();
});
