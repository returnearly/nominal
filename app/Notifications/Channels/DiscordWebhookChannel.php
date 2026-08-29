<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\NotificationChannelMessage;
use App\Support\OutboundHttp;
use Illuminate\Notifications\Notification;

final class DiscordWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof NotificationChannel || ! $notification instanceof NotificationChannelMessage) {
            return;
        }

        $url = $notifiable->configArray()['webhook_url'] ?? $notifiable->configArray()['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return;
        }

        OutboundHttp::json()->post($url, [
            'content' => $notification->text(),
        ])->throw();
    }
}
