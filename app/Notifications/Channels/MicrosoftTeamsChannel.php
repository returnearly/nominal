<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\MonitorAlert;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

final class MicrosoftTeamsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof NotificationChannel || ! $notification instanceof MonitorAlert) {
            return;
        }

        $url = $notifiable->configArray()['webhook_url'] ?? $notifiable->configArray()['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return;
        }

        Http::acceptJson()->asJson()->post($url, [
            'text' => $notification->text(),
        ])->throw();
    }
}
