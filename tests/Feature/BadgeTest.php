<?php

declare(strict_types=1);

use App\Enums\AggregateGranularity;
use App\Enums\MonitorStatus;
use App\Models\CheckAggregate;
use App\Models\CheckResult;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Support\BadgePeriod;
use Illuminate\Support\Str;

it('serves a status svg and shields json badge', function () {
    $monitor = Monitor::factory()->create([
        'name' => 'API',
        'status' => MonitorStatus::Up,
        'enabled' => true,
    ]);

    $this->get('/embed/badges/'.$monitor->id.'/status.svg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
        ->assertHeader('Cache-Control', 'max-age=60, public')
        ->assertSee('status', escape: false)
        ->assertSee('healthy', escape: false);

    $this->getJson('/embed/badges/'.$monitor->id.'/status.json')
        ->assertOk()
        ->assertJson([
            'schemaVersion' => 1,
            'label' => 'status',
            'message' => 'healthy',
            'color' => 'brightgreen',
            'status' => 'up',
        ]);
});

it('renders disabled, unhealthy, and maintenance status badges', function () {
    $disabled = Monitor::factory()->create([
        'status' => MonitorStatus::Up,
        'enabled' => false,
    ]);
    $down = Monitor::factory()->create([
        'status' => MonitorStatus::Down,
        'enabled' => true,
    ]);
    $maintained = Monitor::factory()->create([
        'status' => MonitorStatus::Down,
        'enabled' => true,
    ]);
    MaintenanceWindow::factory()->withMonitors([$maintained])->create();

    $this->getJson('/embed/badges/'.$disabled->id.'/status.json')
        ->assertOk()
        ->assertJson([
            'message' => 'disabled',
            'status' => 'disabled',
        ]);

    $this->getJson('/embed/badges/'.$down->id.'/status.json')
        ->assertOk()
        ->assertJson([
            'message' => 'unhealthy',
            'status' => 'down',
            'color' => 'red',
        ]);

    $this->getJson('/embed/badges/'.$maintained->id.'/status.json')
        ->assertOk()
        ->assertJson([
            'message' => 'maintenance',
            'status' => 'maintenance',
            'color' => 'blueviolet',
        ]);
});

it('computes uptime and latency badges from recent checks', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);

    CheckResult::factory()->count(3)->create([
        'monitor_id' => $monitor->id,
        'success' => true,
        'latency_ms' => 40,
        'checked_at' => now()->subMinutes(10),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'success' => false,
        'latency_ms' => 80,
        'checked_at' => now()->subMinutes(5),
    ]);

    $this->getJson('/embed/badges/'.$monitor->id.'/uptime/1h')
        ->assertOk()
        ->assertJson([
            'schemaVersion' => 1,
            'label' => 'uptime 1h',
            'message' => '75%',
            'period' => '1h',
            'samples' => 4,
        ]);

    $this->getJson('/embed/badges/'.$monitor->id.'/latency')
        ->assertOk()
        ->assertJsonPath('label', 'latency 24h')
        ->assertJsonPath('message', '50ms')
        ->assertJsonPath('latency_ms', 50)
        ->assertJsonPath('period', '24h');

    $this->get('/embed/badges/'.$monitor->id.'/uptime/1h/badge.svg')
        ->assertOk()
        ->assertSee('uptime 1h', escape: false)
        ->assertSee('75%', escape: false);
});

it('uses hourly aggregates for windows longer than an hour', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->create();
    $hour = now()->subHours(3)->startOfHour();

    CheckAggregate::query()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => null,
        'period_start' => $hour,
        'granularity' => AggregateGranularity::Hour,
        'up_count' => 9,
        'down_count' => 1,
        'avg_latency_ms' => 20,
    ]);

    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'success' => true,
        'latency_ms' => 40,
        'checked_at' => now()->subMinutes(10),
    ]);

    $this->getJson('/embed/badges/'.$monitor->id.'/uptime/24h')
        ->assertOk()
        ->assertJsonPath('samples', 11)
        ->assertJsonPath('message', '90.91%');

    $this->getJson('/embed/badges/'.$monitor->id.'/latency/24h')
        ->assertOk()
        ->assertJsonPath('latency_ms', 22)
        ->assertJsonPath('message', '22ms');
});

it('returns n/a when a window has no samples', function () {
    $monitor = Monitor::factory()->create();

    $this->getJson('/embed/badges/'.$monitor->id.'/uptime/7d')
        ->assertOk()
        ->assertJson([
            'message' => 'n/a',
            'uptime' => null,
            'samples' => 0,
        ]);

    $this->getJson('/embed/badges/'.$monitor->id.'/latency/7d')
        ->assertOk()
        ->assertJson([
            'message' => 'n/a',
            'latency_ms' => null,
        ]);
});

it('returns 404 for unknown monitors and invalid periods', function () {
    $this->get('/embed/badges/'.Str::uuid().'/status.svg')->assertNotFound();
    $this->get('/embed/badges/'.Str::uuid().'/uptime/1h')->assertNotFound();

    $monitor = Monitor::factory()->create();

    $this->get('/embed/badges/'.$monitor->id.'/uptime/0h')->assertNotFound();
    $this->get('/embed/badges/'.$monitor->id.'/uptime/99d/badge.svg')->assertNotFound();
    $this->get('/embed/badges/'.$monitor->id.'/uptime/week')->assertNotFound();
});

it('builds public embed urls rather than api urls', function () {
    $monitor = Monitor::factory()->create();

    expect($monitor->statusBadgeSvgUrl())
        ->toEndWith('/embed/badges/'.$monitor->id.'/status.svg')
        ->and($monitor->uptimeBadgeSvgUrl())
        ->toEndWith('/embed/badges/'.$monitor->id.'/uptime/24h/badge.svg')
        ->and($monitor->badgeMarkdown())
        ->not->toContain('/api/');
});

it('exposes badge urls on monitors in graphql', function () {
    $monitor = Monitor::factory()->create(['name' => 'Edge API']);

    graphql('
        query ($id: ID!) {
            monitor(id: $id) {
                statusBadgeUrl
                statusBadgeJsonUrl
                uptimeBadgeUrl
                latencyBadgeUrl
                badgeMarkdown
            }
        }
    ', ['id' => $monitor->id])
        ->assertSuccessful()
        ->assertJsonPath('data.monitor.statusBadgeUrl', $monitor->statusBadgeSvgUrl())
        ->assertJsonPath('data.monitor.statusBadgeJsonUrl', $monitor->statusBadgeJsonUrl())
        ->assertJsonPath('data.monitor.uptimeBadgeUrl', $monitor->uptimeBadgeSvgUrl())
        ->assertJsonPath('data.monitor.latencyBadgeUrl', $monitor->latencyBadgeSvgUrl())
        ->assertJsonPath('data.monitor.badgeMarkdown', $monitor->badgeMarkdown());
});

it('parses badge periods', function () {
    expect(BadgePeriod::parse(null)->key)->toBe('24h')
        ->and(BadgePeriod::parse('1h')->seconds)->toBe(3600)
        ->and(BadgePeriod::parse('1h')->usesAggregates())->toBeFalse()
        ->and(BadgePeriod::parse('24h')->usesAggregates())->toBeTrue()
        ->and(BadgePeriod::parse('7d')->seconds)->toBe(7 * 86400);
});
