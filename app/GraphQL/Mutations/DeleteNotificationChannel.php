<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\NotificationChannel;

final class DeleteNotificationChannel
{
    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        $channel = NotificationChannel::query()->findOrFail($args['id']);

        return (bool) $channel->delete();
    }
}
