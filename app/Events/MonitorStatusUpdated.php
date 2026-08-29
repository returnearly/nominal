<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MonitorStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public MonitorStatus $previous,
    ) {}

    public function broadcastAs(): string
    {
        return 'MonitorStatusUpdated';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('monitors'),
            new PrivateChannel('monitors.'.$this->monitor->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->monitor->id,
            'name' => $this->monitor->name,
            'status' => $this->monitor->effectiveStatus()->value,
            'previous_status' => $this->previous->value,
            'consecutive_successes' => $this->monitor->consecutive_successes,
            'consecutive_failures' => $this->monitor->consecutive_failures,
        ];
    }
}
