<?php

declare(strict_types=1);

use App\Actions\EvaluateAlerting;
use App\Checking\ProbeResult;
use App\Enums\AlertKind;
use App\Enums\MonitorStatus;
use App\Enums\NotificationChannelType;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Filament\Resources\NotificationChannels\Pages\CreateNotificationChannel;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Notifications\MonitorAlert;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const PAGERDUTY_KEY = '0123456789abcdef0123456789abcdef';

it('opens an incident when a monitor goes down and resolves that same incident on recovery', function () {
    Http::fake([
        'https://events.pagerduty.com/v2/enqueue' => Http::response([
            'status' => 'success',
            'message' => 'Event processed',
        ], 202),
    ]);

    $monitor = Monitor::factory()->create([
        'name' => 'Checkout API',
        'target' => 'https://user:secret@example.com/health',
        'description' => 'Restart the worker pool.',
        'tags' => ['prod', 'critical'],
        'status' => MonitorStatus::Down,
        'consecutive_failures' => 3,
    ]);
    $channel = pagerDutyChannel();
    $monitor->notificationChannels()->attach($channel->id, [
        'failure_threshold' => 1,
        'success_threshold' => 1,
        'send_on_resolved' => true,
        'triggered' => false,
    ]);

    EvaluateAlerting::make()->handle($monitor->fresh(['notificationChannels']), pagerDutyFailure('connection refused'));

    $monitor->forceFill([
        'status' => MonitorStatus::Up,
        'consecutive_failures' => 0,
        'consecutive_successes' => 1,
    ])->save();

    EvaluateAlerting::make()->handle($monitor->fresh(['notificationChannels']), pagerDutySuccess());

    Http::assertSentCount(2);

    $events = Http::recorded();
    $trigger = $events[0][0];
    $resolve = $events[1][0];
    $url = MonitorResource::getUrl('view', ['record' => $monitor]);

    expect($trigger->url())->toBe('https://events.pagerduty.com/v2/enqueue')
        ->and($trigger['event_action'])->toBe('trigger')
        ->and($trigger['routing_key'])->toBe(PAGERDUTY_KEY)
        ->and($trigger['dedup_key'])->toBe($monitor->id)
        ->and($trigger['payload']['severity'])->toBe('error')
        ->and($trigger['payload']['source'])->toBe('https://example.com/health')
        ->and($trigger['payload']['summary'])->toBe('Nominal: monitor is down: Checkout API — connection refused')
        ->and($trigger['payload']['class'])->toBe('http')
        ->and($trigger['payload']['component'])->toBe('Checkout API')
        ->and($trigger['payload']['group'])->toBe('prod, critical')
        ->and($trigger['payload']['custom_details']['message'])->toBe('connection refused')
        ->and($trigger['payload']['custom_details']['http_status'])->toBe(500)
        ->and($trigger['payload']['custom_details']['description'])->toBe('Restart the worker pool.')
        ->and($trigger['client'])->toBe('Nominal')
        ->and($trigger['client_url'])->toBe($url)
        ->and($trigger['links'][0]['href'])->toBe($url)
        ->and($resolve['event_action'])->toBe('resolve')
        ->and($resolve['dedup_key'])->toBe($monitor->id)
        ->and($resolve['payload']['severity'])->toBe('info')
        ->and($resolve['payload']['source'])->toBe('https://example.com/health')
        ->and($trigger->body())->not->toContain('secret')
        ->and($resolve->body())->not->toContain('secret');
});

it('updates the open incident when a reminder is due', function () {
    Http::fake([
        'https://events.pagerduty.com/v2/enqueue' => Http::response([
            'status' => 'success',
            'message' => 'Event processed',
        ], 202),
    ]);

    $monitor = Monitor::factory()->create([
        'name' => 'Checkout API',
        'status' => MonitorStatus::Down,
        'consecutive_failures' => 5,
    ]);
    $channel = pagerDutyChannel();
    $monitor->notificationChannels()->attach($channel->id, [
        'failure_threshold' => 1,
        'success_threshold' => 1,
        'send_on_resolved' => true,
        'triggered' => true,
        'reminder_interval_seconds' => 60,
        'last_notified_at' => now()->subMinutes(5),
    ]);

    EvaluateAlerting::make()->handle($monitor->fresh(['notificationChannels']), pagerDutyFailure('still refusing'));

    Http::assertSent(fn ($request): bool => $request['event_action'] === 'trigger'
        && $request['dedup_key'] === $monitor->id
        && $request['payload']['summary'] === 'Nominal: monitor is still down: Checkout API — still refusing'
        && $request['payload']['severity'] === 'error');
});

it('omits database passwords from the pagerduty event', function () {
    Http::fake([
        'https://events.pagerduty.com/v2/enqueue' => Http::response(['status' => 'success'], 202),
    ]);

    $monitor = Monitor::factory()->mysql()->create(['name' => 'Primary']);
    $channel = pagerDutyChannel();

    $channel->notifyNow(new MonitorAlert($monitor, pagerDutyFailure('login failed'), AlertKind::Down));

    Http::assertSent(function ($request) use ($monitor): bool {
        return $request['payload']['source'] === 'mysql://db.example.com:3306/app'
            && $request['dedup_key'] === $monitor->id
            && ! str_contains($request->body(), 'secret');
    });
});

it('truncates the pagerduty summary to 1024 characters', function () {
    Http::fake([
        'https://events.pagerduty.com/v2/enqueue' => Http::response(['status' => 'success'], 202),
    ]);

    $monitor = Monitor::factory()->create(['name' => 'Checkout API']);
    $channel = pagerDutyChannel();

    $channel->notifyNow(new MonitorAlert($monitor, pagerDutyFailure(str_repeat('x', 2000)), AlertKind::Down));

    Http::assertSent(fn ($request): bool => strlen($request['payload']['summary']) === 1024
        && str_starts_with($request['payload']['summary'], 'Nominal: monitor is down: Checkout API — '));
});

it('retries a throttled pagerduty event and does not retry an invalid one', function () {
    Http::fake([
        'https://events.pagerduty.com/v2/enqueue' => Http::sequence()
            ->push(['status' => 'throttled'], 429)
            ->push(['status' => 'success', 'message' => 'Event processed'], 202),
    ]);

    $monitor = Monitor::factory()->create();
    pagerDutyChannel()->notifyNow(new MonitorAlert($monitor, pagerDutyFailure('down'), AlertKind::Down));

    Http::assertSentCount(2);
});

it('does not retry a pagerduty event the api rejects', function () {
    Http::fake([
        'https://events.pagerduty.com/v2/enqueue' => Http::response(['message' => 'Event object is invalid'], 400),
    ]);

    $monitor = Monitor::factory()->create();

    expect(fn () => pagerDutyChannel()->notifyNow(new MonitorAlert($monitor, pagerDutyFailure('down'), AlertKind::Down)))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
});

it('rejects a pagerduty integration key on the channel form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateNotificationChannel::class)
        ->fillForm([
            'name' => 'PagerDuty',
            'type' => NotificationChannelType::Pagerduty,
            'config' => ['routing_key' => 'not-a-real-key'],
        ])
        ->call('create')
        ->assertHasFormErrors(['config.routing_key']);

    expect(NotificationChannel::query()->where('name', 'PagerDuty')->exists())->toBeFalse();
});

function pagerDutyChannel(): NotificationChannel
{
    return NotificationChannel::factory()->create([
        'name' => 'PagerDuty',
        'type' => NotificationChannelType::Pagerduty,
        'config' => ['routing_key' => PAGERDUTY_KEY],
    ]);
}

function pagerDutyFailure(string $message): ProbeResult
{
    return new ProbeResult(
        success: false,
        connected: false,
        latencyMs: 42,
        httpStatus: 500,
        resolvedIp: '203.0.113.10',
        certificateExpiresAt: null,
        message: $message,
        conditionResults: [],
    );
}

function pagerDutySuccess(): ProbeResult
{
    return new ProbeResult(
        success: true,
        connected: true,
        latencyMs: 12,
        httpStatus: 200,
        resolvedIp: '203.0.113.10',
        certificateExpiresAt: null,
        message: null,
        conditionResults: [],
    );
}
