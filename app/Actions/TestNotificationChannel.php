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

    public function handle(NotificationChannel $channel): void
    {
        NotificationChannelConfig::assertValid($channel->type, $channel->configArray());

        $channel->notifyNow(new ChannelTestNotification($channel));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fromForm(NotificationChannel $record, array $data): NotificationChannel
    {
        $type = $this->type($data['type'] ?? $record->type);
        $config = NotificationChannelConfig::normalize($type, $data['config'] ?? []);

        $channel = new NotificationChannel([
            'name' => $data['name'] ?? $record->name,
            'type' => $type,
            'config' => $config,
        ]);
        $channel->id = $record->id;

        $this->handle($channel);

        return $channel;
    }

    private function type(mixed $type): NotificationChannelType
    {
        if ($type instanceof NotificationChannelType) {
            return $type;
        }

        return EnumValue::parse(NotificationChannelType::class, $type);
    }
}
