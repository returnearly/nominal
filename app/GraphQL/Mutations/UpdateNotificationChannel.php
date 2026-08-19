<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveNotificationChannel;
use App\Models\NotificationChannel;

final class UpdateNotificationChannel
{
    public function __construct(
        private readonly SaveNotificationChannel $saveNotificationChannel,
    ) {}

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): NotificationChannel
    {
        $channel = NotificationChannel::query()->findOrFail($args['id']);
        $input = $args['input'];

        if (isset($input['config']) && is_array($input['config'])) {
            $config = [];

            foreach ($input['config'] as $pair) {
                $config[$pair['key']] = $pair['value'];
            }

            $input['config'] = $config;
        }

        if (isset($input['type'])) {
            $input['type'] = strtolower((string) $input['type']);
        }

        return $this->saveNotificationChannel->handle($input, $channel);
    }
}
