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
        return $this->saveNotificationChannel->handle($this->normalize($args['input']));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
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

        return $input;
    }
}
