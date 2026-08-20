<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Enums\AlertKind;
use App\Models\NotificationChannel;
use App\Notifications\MonitorAlert;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

final class PagerDutyChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof NotificationChannel || ! $notification instanceof MonitorAlert) {
            return;
        }

        $routingKey = $notifiable->configArray()['routing_key']
            ?? $notifiable->configArray()['integration_key']
            ?? null;

        if (! is_string($routingKey) || $routingKey === '') {
            return;
        }

        Http::acceptJson()->asJson()->post('https://events.pagerduty.com/v2/enqueue', [
            'routing_key' => $routingKey,
            'event_action' => $notification->kind === AlertKind::Recovered ? 'resolve' : 'trigger',
            'dedup_key' => $notification->monitor->id,
            'payload' => [
                'summary' => $notification->headline().': '.$notification->monitor->name,
                'source' => 'nominal',
                'severity' => $notification->kind === AlertKind::Recovered ? 'info' : 'error',
                'component' => $notification->monitor->target,
                'class' => $notification->monitor->type->value,
            ],
        ])->throw();
    }
}
