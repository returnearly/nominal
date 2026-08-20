<?php

declare(strict_types=1);

use App\Actions\EvaluateAlerting;
use App\Checking\ProbeResult;
use App\Enums\AlertKind;
use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Notifications\MonitorAlert;
use Illuminate\Support\Facades\Notification;

function failedResult(): ProbeResult
{
    return new ProbeResult(
        success: false,
        connected: false,
        latencyMs: 9,
        httpStatus: 500,
        resolvedIp: null,
        certificateExpiresAt: null,
        message: 'down',
        conditionResults: [],
    );
}

function passedResult(): ProbeResult
{
    return new ProbeResult(
        success: true,
        connected: true,
        latencyMs: 9,
        httpStatus: 200,
        resolvedIp: '1.1.1.1',
        certificateExpiresAt: null,
        message: null,
        conditionResults: [],
    );
}

it('notifies only after the failure threshold', function () {
    Notification::fake();

    $monitor = Monitor::factory()->create([
        'status' => MonitorStatus::Down,
        'consecutive_failures' => 2,
    ]);
    $channel = NotificationChannel::factory()->create();
    $monitor->notificationChannels()->attach($channel->id, [
        'failure_threshold' => 3,
        'success_threshold' => 2,
        'send_on_resolved' => true,
        'triggered' => false,
    ]);

    EvaluateAlerting::make()->handle($monitor, failedResult());
    Notification::assertNothingSent();

    $monitor->consecutive_failures = 3;
    $monitor->load('notificationChannels');
    EvaluateAlerting::make()->handle($monitor, failedResult());

    Notification::assertSentTo($channel, MonitorAlert::class, fn (MonitorAlert $alert): bool => $alert->kind === AlertKind::Down);
});

it('sends a recovery notification and resolves the incident', function () {
    Notification::fake();

    $monitor = Monitor::factory()->create([
        'status' => MonitorStatus::Up,
        'consecutive_successes' => 2,
    ]);
    $channel = NotificationChannel::factory()->create();
    $monitor->notificationChannels()->attach($channel->id, [
        'failure_threshold' => 3,
        'success_threshold' => 2,
        'send_on_resolved' => true,
        'triggered' => true,
    ]);

    EvaluateAlerting::make()->handle($monitor->fresh(['notificationChannels']), passedResult());

    Notification::assertSentTo($channel, MonitorAlert::class, fn (MonitorAlert $alert): bool => $alert->kind === AlertKind::Recovered);
    expect($monitor->fresh()->notificationChannels->first()->pivot->triggered)->toBeFalse();
});

it('includes the runbook and tags on down alerts', function () {
    Notification::fake();

    $monitor = Monitor::factory()->create([
        'name' => 'Checkout API',
        'status' => MonitorStatus::Down,
        'consecutive_failures' => 3,
        'description' => 'Owned by payments. Restart the worker pool.',
        'tags' => ['prod', 'critical'],
        'group' => 'payments',
    ]);
    $channel = NotificationChannel::factory()->create();
    $monitor->notificationChannels()->attach($channel->id, [
        'failure_threshold' => 3,
        'success_threshold' => 2,
        'send_on_resolved' => true,
        'triggered' => false,
    ]);

    EvaluateAlerting::make()->handle($monitor, failedResult());

    Notification::assertSentTo($channel, MonitorAlert::class, function (MonitorAlert $alert) use ($monitor): bool {
        $payload = $alert->toWebhook();

        expect($alert->text())->toContain('Owned by payments. Restart the worker pool.')
            ->and($payload['monitor']['description'])->toBe('Owned by payments. Restart the worker pool.')
            ->and($payload['monitor']['tags'])->toBe(['prod', 'critical'])
            ->and($payload['monitor']['group'])->toBe('payments')
            ->and($payload['monitor']['id'])->toBe($monitor->id);

        return $alert->kind === AlertKind::Down;
    });
});
