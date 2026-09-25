<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\NotificationChannelMessage;
use App\Notifications\PagerDutyEvent;
use App\Support\OutboundHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Throwable;

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

        foreach ($notification->toPagerDutyEvents() as $event) {
            OutboundHttp::json()
                ->retry(3, 200, fn (Throwable $exception): bool => $this->shouldRetry($exception))
                ->post(PagerDutyEvent::Endpoint, [
                    ...$event,
                    'routing_key' => $routingKey,
                ])
                ->throw();
        }
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
    }
}
