<?php

declare(strict_types=1);

use App\Enums\MonitorStatus;
use App\Events\CheckCompleted;
use App\Events\MonitorStatusUpdated;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts check completed on the monitors channels', function () {
    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create();
    $result = CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
    ]);

    $event = new CheckCompleted($monitor, $probe, $result);

    expect($event->broadcastAs())->toBe('CheckCompleted')
        ->and(array_map(
            fn (PrivateChannel $channel): string => $channel->name,
            $event->broadcastOn(),
        ))->toBe([
            'private-monitors',
            'private-monitors.'.$monitor->id,
        ])
        ->and($event->broadcastWith()['monitor_id'])->toBe($monitor->id)
        ->and($event->broadcastWith()['id'])->toBe($result->id);
});

it('broadcasts monitor status changes on the monitors channels', function () {
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Down]);

    $event = new MonitorStatusUpdated($monitor, MonitorStatus::Up);

    expect($event->broadcastAs())->toBe('MonitorStatusUpdated')
        ->and(array_map(
            fn (PrivateChannel $channel): string => $channel->name,
            $event->broadcastOn(),
        ))->toBe([
            'private-monitors',
            'private-monitors.'.$monitor->id,
        ])
        ->and($event->broadcastWith())->toMatchArray([
            'id' => $monitor->id,
            'status' => MonitorStatus::Down->value,
            'previous_status' => MonitorStatus::Up->value,
        ]);
});

it('authorizes the monitors channel for a session user', function () {
    enableReverbBroadcasting();

    $this->actingAs(User::factory()->create())
        ->post('/broadcasting/auth', monitorChannelAuthPayload())
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('authorizes the monitors channel with a sanctum token', function () {
    enableReverbBroadcasting();

    $user = User::factory()->create();

    $this->withToken($user->createToken('graphql')->plainTextToken)
        ->post('/broadcasting/auth', monitorChannelAuthPayload())
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('authorizes the monitors channel as the anonymous operator', function () {
    enableReverbBroadcasting();
    config(['nominal.interface_auth' => 'none']);

    $this->post('/broadcasting/auth', monitorChannelAuthPayload())
        ->assertOk()
        ->assertJsonStructure(['auth']);

    expect(auth()->user()?->email)->toBe('operator@nominal.local');
});

it('rejects guests from the monitors channel when login is required', function () {
    enableReverbBroadcasting();

    $this->postJson('/broadcasting/auth', monitorChannelAuthPayload())
        ->assertUnauthorized();
});

it('authorizes the filament notification channel for the current user', function () {
    enableReverbBroadcasting();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', userChannelAuthPayload($user))
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('rejects the filament notification channel for another user', function () {
    enableReverbBroadcasting();

    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', userChannelAuthPayload($other))
        ->assertForbidden();
});

function enableReverbBroadcasting(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'nominal-key',
        'broadcasting.connections.reverb.secret' => 'nominal-secret',
        'broadcasting.connections.reverb.app_id' => 'nominal',
        'broadcasting.connections.reverb.options' => [
            'host' => 'localhost',
            'port' => 8080,
            'scheme' => 'http',
            'useTLS' => false,
        ],
    ]);

    require base_path('routes/channels.php');
}

/**
 * @return array{socket_id: string, channel_name: string}
 */
function monitorChannelAuthPayload(): array
{
    return [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-monitors',
    ];
}

/**
 * @return array{socket_id: string, channel_name: string}
 */
function userChannelAuthPayload(User $user): array
{
    return [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-'.$user->broadcastChannel(),
    ];
}
