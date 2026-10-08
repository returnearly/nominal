<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\NotificationChannel;
use App\Models\PushSubscription;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class UnsubscribePushDevice implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(NotificationChannel $channel, string $endpoint): void
    {
        PushSubscription::query()
            ->where('notification_channel_id', $channel->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
            ->delete();
    }

    public function byId(NotificationChannel $channel, string $subscriptionId): void
    {
        PushSubscription::query()
            ->where('notification_channel_id', $channel->id)
            ->whereKey($subscriptionId)
            ->delete();
    }
}
