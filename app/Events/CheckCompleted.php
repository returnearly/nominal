<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CheckCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public ?Probe $probe,
        public CheckResult $result,
    ) {}

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
            'id' => $this->result->id,
            'monitor_id' => $this->monitor->id,
            'probe_id' => $this->probe?->id,
            'success' => $this->result->success,
            'latency_ms' => $this->result->latency_ms,
            'http_status' => $this->result->http_status,
            'checked_at' => $this->result->checked_at?->toIso8601String(),
            'message' => $this->result->message,
        ];
    }
}
