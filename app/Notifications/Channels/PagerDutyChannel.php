<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\NotificationChannelMessage;
use App\Support\OutboundHttp;
use Illuminate\Notifications\Notification;

final class PagerDutyChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof NotificationChannel || ! $notification instanceof NotificationChannelMessage) {
            return;
        }

        $routingKey = $notifiable->configArray()['routing_key']
            ?? $notifiable->configArray()['integration_key']
            ?? null;

        if (! is_string($routingKey) || $routingKey === '') {
            return;
        }

        OutboundHttp::json()->post('https://events.pagerduty.com/v2/enqueue', [
            ...$notification->toPagerDuty(),
            'routing_key' => $routingKey,
        ])->throw();
    }
}
