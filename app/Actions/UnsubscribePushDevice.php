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

    public function handle(NotificationChannel $channel, string $endpointOrId): void
    {
        $query = PushSubscription::query()->where('notification_channel_id', $channel->id);

        if (str_starts_with($endpointOrId, 'http://') || str_starts_with($endpointOrId, 'https://')) {
            $query->where('endpoint_hash', PushSubscription::hashEndpoint($endpointOrId));
        } else {
            $query->whereKey($endpointOrId);
        }

        $query->delete();
    }
}
