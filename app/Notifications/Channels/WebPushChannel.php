<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Contracts\SendsWebPush;
use App\Models\NotificationChannel;
use App\Models\PushSubscription;
use App\Notifications\ChannelTestNotification;
use App\Notifications\MonitorAlert;
use App\Notifications\NotificationChannelMessage;
use App\Support\WebPushVapid;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class WebPushChannel
{
    public function __construct(
        private readonly ?SendsWebPush $sender = null,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof NotificationChannel || ! $notification instanceof NotificationChannelMessage) {
            return;
        }

        $vapid = WebPushVapid::fromConfig($notifiable->configArray());

        if ($vapid === null) {
            throw new RuntimeException('Browser channel is missing VAPID keys.');
        }

        $subscriptions = $notifiable->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            if ($notification instanceof ChannelTestNotification) {
                throw new RuntimeException('No devices subscribed to this browser channel.');
            }

            return;
        }

        $payload = [
            'title' => $notification->headline(),
            'body' => $notification->text(),
            'url' => $notification instanceof MonitorAlert
                ? url('/admin/monitors/'.$notification->monitor->id)
                : url('/admin'),
        ];

        $gone = ($this->sender ?? app(SendsWebPush::class))->send($vapid, $subscriptions, $payload);

        if ($gone !== []) {
            PushSubscription::query()->whereIn('id', $gone)->delete();
            Log::info('Removed gone web push subscriptions', ['ids' => $gone]);
        }
    }
}
