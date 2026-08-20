<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReceivePushHeartbeat;
use App\Enums\MonitorType;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PushHeartbeatController
{
    public function __invoke(Request $request, string $token, ReceivePushHeartbeat $receive): JsonResponse
    {
        $monitor = Monitor::query()
            ->where('type', MonitorType::Push)
            ->where('push_token', $token)
            ->firstOrFail();

        abort_unless($monitor->enabled, 404);

        $latency = $request->integer('latency');

        if ($latency === 0) {
            $latency = $request->integer('ping');
        }

        $check = $receive->handle($monitor, $latency > 0 ? $latency : null);

        return response()->json([
            'ok' => true,
            'id' => $check->id,
            'success' => $check->success,
        ]);
    }
}
