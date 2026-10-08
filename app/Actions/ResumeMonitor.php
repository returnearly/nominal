<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MonitorStatus;
use App\Events\MonitorStatusUpdated;
use App\Models\Monitor;
use Illuminate\Broadcasting\BroadcastException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ResumeMonitor implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(Monitor $monitor): Monitor
    {
        if ($monitor->status !== MonitorStatus::Paused) {
            return $monitor;
        }

        $previous = $monitor->status;
        $monitor->status = MonitorStatus::Pending;
        $monitor->next_check_at = now();
        $monitor->last_status_changed_at = now();
        $monitor->save();

        $this->broadcast($monitor, $previous);

        return $monitor;
    }

    private function broadcast(Monitor $monitor, MonitorStatus $previous): void
    {
        try {
            MonitorStatusUpdated::dispatch($monitor, $previous);
        } catch (BroadcastException $exception) {
            report($exception);
        }
    }
}
