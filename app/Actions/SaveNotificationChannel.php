<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Support\EnumValue;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SaveNotificationChannel implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?NotificationChannel $channel = null): NotificationChannel
    {
        $channel ??= new NotificationChannel;
        $type = $input['type'] ?? $channel->type;

        $channel->fill([
            'name' => $input['name'] ?? $channel->name,
            'type' => $type instanceof NotificationChannelType
                ? $type
                : EnumValue::parse(NotificationChannelType::class, $type),
            'config' => $input['config'] ?? $channel->config ?? [],
        ]);

        $channel->save();

        return $channel->fresh() ?? $channel;
    }
}
