<?php

declare(strict_types=1);

use App\Actions\RegenerateBrowserChannelKeys;
use App\Actions\SaveNotificationChannel;
use App\Actions\SubscribePushDevice;
use App\Actions\TestNotificationChannel;
use App\Actions\UnsubscribePushDevice;
use App\Contracts\SendsWebPush;
use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\ChannelTestNotification;
use App\Support\WebPushVapid;

it('creates a browser channel with generated vapid keys', function () {
    $channel = SaveNotificationChannel::make()->handle([
        'name' => 'Ops browsers',
        'type' => NotificationChannelType::Browser,
    ]);

    expect($channel->type)->toBe(NotificationChannelType::Browser)
        ->and($channel->configArray())->toHaveKeys(['vapid_public_key', 'vapid_private_key', 'vapid_subject'])
        ->and($channel->configArray()['vapid_public_key'])->not->toBeEmpty()
        ->and($channel->destination)->toBe('No devices')
        ->and($channel->deliversVia())->toBe([WebPushChannel::class]);
});

it('keeps vapid keys when updating a browser channel name', function () {
    $channel = NotificationChannel::factory()->browser()->create();
    $keys = $channel->configArray();

    $updated = SaveNotificationChannel::make()->handle([
        'name' => 'Renamed browsers',
        'type' => NotificationChannelType::Browser,
        'config' => [],
    ], $channel);

    expect($updated->name)->toBe('Renamed browsers')
        ->and($updated->configArray())->toBe($keys);
});

it('subscribes and unsubscribes a device', function () {
    $channel = NotificationChannel::factory()->browser()->create();
    $user = User::factory()->create();

    $subscription = SubscribePushDevice::make()->handle($channel, [
        'endpoint' => 'https://push.example.com/s/abc',
        'public_key' => 'pk',
        'auth_token' => 'auth',
        'content_encoding' => 'aes128gcm',
        'user_agent' => 'Mozilla/5.0',
    ], $user);

    expect($subscription->notification_channel_id)->toBe($channel->id)
        ->and($subscription->user_id)->toBe($user->id)
        ->and($channel->fresh()->destination)->toBe('1 device');

    UnsubscribePushDevice::make()->handle($channel, 'https://push.example.com/s/abc');

    expect(PushSubscription::query()->count())->toBe(0)
        ->and($channel->fresh()->destination)->toBe('No devices');
});

it('regenerates vapid keys and clears devices', function () {
    $channel = NotificationChannel::factory()->browser()->create();
    $before = $channel->configArray()['vapid_public_key'];

    PushSubscription::factory()->create([
        'notification_channel_id' => $channel->id,
    ]);

    $updated = RegenerateBrowserChannelKeys::make()->handle($channel);

    expect($updated->configArray()['vapid_public_key'])->not->toBe($before)
        ->and(PushSubscription::query()->count())->toBe(0);
});

it('sends a test push through the web push sender', function () {
    $channel = NotificationChannel::factory()->browser()->create();
    PushSubscription::factory()->create([
        'notification_channel_id' => $channel->id,
    ]);

    $sent = false;

    $sender = new class($channel, $sent) implements SendsWebPush
    {
        public function __construct(
            private NotificationChannel $channel,
            private bool &$sent,
        ) {}

        public function send(array $vapid, iterable $subscriptions, array $payload): array
        {
            expect($vapid['vapid_public_key'])->toBe($this->channel->configArray()['vapid_public_key'])
                ->and($payload['title'])->toBe('Nominal: test notification')
                ->and(collect($subscriptions))->toHaveCount(1);

            $this->sent = true;

            return [];
        }
    };

    (new WebPushChannel($sender))->send($channel, new ChannelTestNotification($channel));

    expect($sent)->toBeTrue();
});

it('removes gone subscriptions after a failed push', function () {
    $channel = NotificationChannel::factory()->browser()->create();
    $gone = PushSubscription::factory()->create([
        'notification_channel_id' => $channel->id,
    ]);
    $keep = PushSubscription::factory()->create([
        'notification_channel_id' => $channel->id,
    ]);

    $sender = new class($gone->id) implements SendsWebPush
    {
        public function __construct(private string $goneId) {}

        public function send(array $vapid, iterable $subscriptions, array $payload): array
        {
            return [$this->goneId];
        }
    };

    (new WebPushChannel($sender))->send($channel, new ChannelTestNotification($channel));

    expect(PushSubscription::query()->whereKey($gone->id)->exists())->toBeFalse()
        ->and(PushSubscription::query()->whereKey($keep->id)->exists())->toBeTrue();
});

it('fails a browser test when no devices are subscribed', function () {
    $channel = NotificationChannel::factory()->browser()->create();

    TestNotificationChannel::make()->handle($channel);
})->throws(RuntimeException::class, 'No devices subscribed');

it('creates vapid material through the helper', function () {
    $keys = WebPushVapid::generate('mailto:ops@example.com');

    expect($keys)->toHaveKeys(['vapid_public_key', 'vapid_private_key', 'vapid_subject'])
        ->and($keys['vapid_subject'])->toBe('mailto:ops@example.com');
});
