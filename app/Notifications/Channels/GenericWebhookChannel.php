<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\MonitorAlert;
use App\Support\OutboundHttp;
use Illuminate\Notifications\Notification;

final class GenericWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof NotificationChannel || ! $notification instanceof MonitorAlert) {
            return;
        }

        $url = $notifiable->configArray()['url'] ?? $notifiable->configArray()['webhook_url'] ?? null;

        if (! is_string($url) || $url === '') {
            return;
        }

        OutboundHttp::json()->post($url, $notification->toWebhook())->throw();
    }
}
