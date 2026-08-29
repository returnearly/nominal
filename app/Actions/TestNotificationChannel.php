<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Notifications\ChannelTestNotification;
use App\Support\EnumValue;
use App\Support\NotificationChannelConfig;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class TestNotificationChannel implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(NotificationChannel $channel, array $data = []): void
    {
        if ($data !== []) {
            $id = $channel->id;
            $type = $this->type($data['type'] ?? $channel->type);
            $config = NotificationChannelConfig::normalize($type, $data['config'] ?? []);

            $channel = new NotificationChannel([
                'name' => $data['name'] ?? $channel->name,
                'type' => $type,
                'config' => $config,
            ]);
            $channel->id = $id;
        }

        NotificationChannelConfig::assertValid($channel->type, $channel->configArray());

        $channel->notifyNow(new ChannelTestNotification($channel));
    }

    private function type(mixed $type): NotificationChannelType
    {
        if ($type instanceof NotificationChannelType) {
            return $type;
        }

        return EnumValue::parse(NotificationChannelType::class, $type);
    }
}
