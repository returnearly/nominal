<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReceiveHeartbeat;
use App\Enums\HeartbeatSignal;
use App\Enums\MonitorType;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HeartbeatController
{
    public function __invoke(Request $request, ReceiveHeartbeat $receive, string $token, ?string $signal = null): JsonResponse
    {
        $monitor = Monitor::query()
            ->where('type', MonitorType::Heartbeat)
            ->where('heartbeat_token', $token)
            ->firstOrFail();

        abort_unless($monitor->enabled, 404);

        $latency = $request->integer('latency');

        if ($latency === 0) {
            $latency = $request->integer('ping');
        }

        $check = $receive->handle(
            $monitor,
            HeartbeatSignal::fromRoute($signal),
            $latency > 0 ? $latency : null,
        );

        if ($check === null) {
            return response()->json([
                'ok' => true,
                'started' => true,
            ]);
        }

        return response()->json([
            'ok' => true,
            'id' => $check->id,
            'success' => $check->success,
        ]);
    }
}
