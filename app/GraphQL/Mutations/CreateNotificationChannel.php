<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveNotificationChannel;
use App\Models\NotificationChannel;

final class CreateNotificationChannel
{
    public function __construct(
        private readonly SaveNotificationChannel $saveNotificationChannel,
    ) {}

    /**
     * @param  array{input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): NotificationChannel
    {
        return $this->saveNotificationChannel->handle($args['input']);
    }
}
