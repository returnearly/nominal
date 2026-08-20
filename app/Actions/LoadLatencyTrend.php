<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AggregateGranularity;
use App\Models\CheckAggregate;
use App\Models\Monitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class LoadLatencyTrend implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @return Collection<int, object{latency_ms: int, checked_at: Carbon, success: bool, probe: null}>
     */
    public function handle(Monitor $monitor, int $hours = 24): Collection
    {
        $aggregates = CheckAggregate::query()
            ->where('monitor_id', $monitor->id)
            ->whereNull('probe_id')
            ->where('granularity', AggregateGranularity::Hour)
            ->where('period_start', '>=', now()->subHours($hours)->startOfHour())
            ->whereNotNull('avg_latency_ms')
            ->orderBy('period_start')
            ->get();

        if ($aggregates->isEmpty()) {
            return collect();
        }

        return $aggregates->map(fn (CheckAggregate $row): object => (object) [
            'latency_ms' => $row->avg_latency_ms,
            'checked_at' => $row->period_start,
            'success' => $row->down_count === 0,
            'probe' => null,
        ]);
    }
}
