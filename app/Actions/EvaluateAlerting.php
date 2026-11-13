<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Enums\AlertKind;
use App\Models\Monitor;
use App\Models\MonitorNotificationChannel;
use App\Models\NotificationChannel;
use App\Notifications\MonitorAlert;
use Illuminate\Support\Carbon;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class EvaluateAlerting implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(Monitor $monitor, ProbeResult $result): void
    {
        if ($monitor->isUnderMaintenance()) {
            return;
        }

        $monitor->loadMissing('notificationChannels');

        foreach ($monitor->notificationChannels as $channel) {
            /** @var MonitorNotificationChannel $pivot */
            $pivot = $channel->pivot;
            $kind = $this->kind($monitor, $result, $pivot, $channel);

            if ($kind === AlertKind::Recovered && ! $pivot->send_on_resolved) {
                $this->mark($monitor, $channel, $pivot, triggered: false, notified: false);

                continue;
            }

            if ($kind === null) {
                continue;
            }

            $channel->notify(new MonitorAlert($monitor, $result, $kind));
            $this->mark($monitor, $channel, $pivot, triggered: $kind !== AlertKind::Recovered, notified: true);
        }
    }

    private function kind(
        Monitor $monitor,
        ProbeResult $result,
        MonitorNotificationChannel $pivot,
        NotificationChannel $channel,
    ): ?AlertKind {
        if (! $result->success) {
            if ($monitor->consecutive_failures < $pivot->failure_threshold) {
                return null;
            }

            if (! $pivot->triggered) {
                return AlertKind::Down;
            }

            return $this->reminderDue($pivot) ? AlertKind::Reminder : null;
        }

        if (! $pivot->triggered) {
            return null;
        }

        if ($monitor->consecutive_successes < $pivot->success_threshold) {
            return null;
        }

        if (! $this->openLongEnough($pivot, $channel)) {
            return null;
        }

        return AlertKind::Recovered;
    }

    private function openLongEnough(MonitorNotificationChannel $pivot, NotificationChannel $channel): bool
    {
        if ($channel->minimum_open_seconds < 1) {
            return true;
        }

        if ($pivot->triggered_at === null) {
            return true;
        }

        return $pivot->triggered_at
            ->copy()
            ->addSeconds($channel->minimum_open_seconds)
            ->lte(Carbon::now());
    }

    private function reminderDue(MonitorNotificationChannel $pivot): bool
    {
        if ($pivot->reminder_interval_seconds === null || $pivot->reminder_interval_seconds < 1) {
            return false;
        }

        if ($pivot->last_notified_at === null) {
            return true;
        }

        return $pivot->last_notified_at
            ->copy()
            ->addSeconds($pivot->reminder_interval_seconds)
            ->lte(Carbon::now());
    }

    private function mark(
        Monitor $monitor,
        NotificationChannel $channel,
        MonitorNotificationChannel $pivot,
        bool $triggered,
        bool $notified,
    ): void {
        $attributes = ['triggered' => $triggered];

        if ($triggered && ! $pivot->triggered) {
            $attributes['triggered_at'] = now();
        }

        if (! $triggered) {
            $attributes['triggered_at'] = null;
        }

        if ($notified) {
            $attributes['last_notified_at'] = now();
        }

        $monitor->notificationChannels()->updateExistingPivot($channel->id, $attributes);
    }
}
