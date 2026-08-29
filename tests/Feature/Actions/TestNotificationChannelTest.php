<?php

declare(strict_types=1);

use App\Actions\TestNotificationChannel;
use App\Checking\ProbeResult;
use App\Enums\AlertKind;
use App\Enums\NotificationChannelType;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Notifications\Channels\SmtpMailChannel;
use App\Notifications\ChannelTestNotification;
use App\Notifications\MonitorAlert;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

it('sends a test email through a mail channel', function () {
    Notification::fake();

    $channel = NotificationChannel::factory()->mail('ops@example.com')->create(['name' => 'Ops mail']);

    TestNotificationChannel::make()->handle($channel);

    Notification::assertSentTo($channel, ChannelTestNotification::class, function (ChannelTestNotification $notification) use ($channel): bool {
        $mail = $notification->toMail($channel);

        expect($mail->subject)->toBe('Nominal: test notification')
            ->and($notification->text())->toContain('Ops mail')
            ->and($channel->deliversVia())->toBe([SmtpMailChannel::class]);

        return true;
    });
});

it('sends a test email through the environment mailer', function () {
    $channel = NotificationChannel::factory()->mail('ops@example.com')->create(['name' => 'Ops mail']);

    TestNotificationChannel::make()->handle($channel);

    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->getOriginalMessage()->getTo()[0]->getAddress())->toBe('ops@example.com');
});

it('uses the channel from address on the mail message', function () {
    $channel = NotificationChannel::factory()->mail()->create([
        'name' => 'Ops mail',
        'config' => [
            'to' => 'ops@example.com',
            'from_address' => 'alerts@acme.test',
            'from_name' => 'Acme',
        ],
    ]);

    $mail = (new ChannelTestNotification($channel))->toMail($channel);

    expect($mail->from)->toBe(['alerts@acme.test', 'Acme']);
});

it('posts a test payload to webhook channels', function (NotificationChannelType $type, string $url, string $key) {
    Http::fake();

    $channel = NotificationChannel::factory()->create([
        'name' => 'Ops',
        'type' => $type,
        'config' => [$key => $url],
    ]);

    TestNotificationChannel::make()->handle($channel);

    Http::assertSent(fn (Request $request): bool => $request->url() === $url
        && str_contains((string) $request->body(), 'test notification'));
})->with([
    [NotificationChannelType::Slack, 'https://hooks.slack.com/services/T/B/xxx', 'webhook_url'],
    [NotificationChannelType::Discord, 'https://discord.com/api/webhooks/1/abc', 'webhook_url'],
    [NotificationChannelType::MicrosoftTeams, 'https://outlook.office.com/webhook/xxx', 'webhook_url'],
    [NotificationChannelType::Webhook, 'https://example.com/hooks/nominal', 'url'],
]);

it('posts a generic webhook test with an event of test', function () {
    Http::fake();

    $channel = NotificationChannel::factory()->create([
        'name' => 'Hooks',
        'type' => NotificationChannelType::Webhook,
        'config' => ['url' => 'https://example.com/hooks/nominal'],
    ]);

    TestNotificationChannel::make()->handle($channel);

    Http::assertSent(function (Request $request) use ($channel): bool {
        return $request->url() === 'https://example.com/hooks/nominal'
            && $request['event'] === 'test'
            && $request['headline'] === 'Nominal: test notification'
            && $request['channel']['id'] === $channel->id
            && $request['channel']['name'] === 'Hooks';
    });
});

it('opens a pagerduty test incident', function () {
    Http::fake();

    $channel = NotificationChannel::factory()->create([
        'name' => 'PagerDuty',
        'type' => NotificationChannelType::Pagerduty,
        'config' => ['routing_key' => 'R0123456789ABCDEF'],
    ]);

    TestNotificationChannel::make()->handle($channel);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://events.pagerduty.com/v2/enqueue'
        && $request['routing_key'] === 'R0123456789ABCDEF'
        && $request['event_action'] === 'trigger'
        && $request['dedup_key'] === 'nominal-test-'.$channel->id
        && $request['payload']['severity'] === 'info');
});

it('sends using form values without persisting them', function () {
    Http::fake();

    $channel = NotificationChannel::factory()->create([
        'name' => 'Hooks',
        'config' => ['url' => 'https://example.com/hooks/old'],
    ]);

    TestNotificationChannel::make()->handle($channel, [
        'name' => 'Hooks',
        'type' => NotificationChannelType::Webhook,
        'config' => ['url' => 'https://example.com/hooks/new'],
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.com/hooks/new');
    expect($channel->fresh()?->configArray())->toBe(['url' => 'https://example.com/hooks/old']);
});

it('rejects a test when the channel is missing required config', function () {
    $channel = NotificationChannel::factory()->mail()->create(['config' => []]);

    TestNotificationChannel::make()->handle($channel);
})->throws(ValidationException::class);

it('fails when the destination rejects the test', function () {
    Http::fake([
        '*' => Http::response('nope', 500),
    ]);

    $channel = NotificationChannel::factory()->create();

    TestNotificationChannel::make()->handle($channel);
})->throws(RequestException::class);

it('still delivers monitor alerts through webhook channels', function () {
    Http::fake();

    $monitor = Monitor::factory()->create(['name' => 'Checkout API', 'target' => 'https://example.com']);
    $channel = NotificationChannel::factory()->slack()->create();

    $channel->notifyNow(new MonitorAlert(
        $monitor,
        new ProbeResult(
            success: false,
            connected: false,
            latencyMs: 9,
            httpStatus: 500,
            resolvedIp: null,
            certificateExpiresAt: null,
            message: 'down',
            conditionResults: [],
        ),
        AlertKind::Down,
    ));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hooks.slack.com/services/T/B/xxx'
        && str_contains((string) $request['text'], 'monitor is down')
        && str_contains((string) $request['text'], 'Checkout API'));
});
