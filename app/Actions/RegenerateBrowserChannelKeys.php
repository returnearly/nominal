<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Support\WebPushVapid;
use Illuminate\Validation\ValidationException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class RegenerateBrowserChannelKeys implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(NotificationChannel $channel): NotificationChannel
    {
        if ($channel->type !== NotificationChannelType::Browser) {
            throw ValidationException::withMessages([
                'type' => 'Only browser channels have VAPID keys.',
            ]);
        }

        $channel->pushSubscriptions()->delete();
        $channel->forceFill([
            'config' => WebPushVapid::generate(),
        ])->save();

        return $channel->fresh() ?? $channel;
    }
}
