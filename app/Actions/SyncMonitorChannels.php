<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SyncMonitorChannels implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  list<string>  $channelIds
     */
    public function handle(Monitor $monitor, array $channelIds): Monitor
    {
        $existing = $monitor->notificationChannels()->pluck('notification_channels.id')->all();
        $keep = [];

        foreach ($channelIds as $channelId) {
            $keep[] = $channelId;

            if (in_array($channelId, $existing, true)) {
                continue;
            }

            NotificationChannel::query()->findOrFail($channelId);

            $monitor->notificationChannels()->attach($channelId, [
                'failure_threshold' => 3,
                'success_threshold' => 2,
                'send_on_resolved' => true,
                'triggered' => false,
            ]);
        }

        $monitor->notificationChannels()->detach(array_diff($existing, $keep));

        return $monitor->fresh(['notificationChannels', 'conditions', 'probes']) ?? $monitor;
    }
}
