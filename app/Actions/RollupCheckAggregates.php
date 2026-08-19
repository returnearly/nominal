<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AggregateGranularity;
use App\Models\CheckAggregate;
use App\Models\CheckResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class RollupCheckAggregates implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(?Carbon $hourStart = null): int
    {
        $periodStart = ($hourStart ?? now()->subHour()->startOfHour())->copy()->startOfHour();
        $periodEnd = $periodStart->copy()->addHour();
        $written = 0;

        $groups = CheckResult::query()
            ->where('checked_at', '>=', $periodStart)
            ->where('checked_at', '<', $periodEnd)
            ->get()
            ->groupBy(fn (CheckResult $result): string => $result->monitor_id.'|'.$result->probe_id);

        foreach ($groups as $results) {
            $first = $results->first();
            $this->upsert(
                $first->monitor_id,
                $first->probe_id,
                $periodStart,
                $results,
            );
            $written++;
        }

        $byMonitor = CheckResult::query()
            ->where('checked_at', '>=', $periodStart)
            ->where('checked_at', '<', $periodEnd)
            ->get()
            ->groupBy('monitor_id');

        foreach ($byMonitor as $monitorId => $results) {
            $this->upsert((string) $monitorId, null, $periodStart, $results);
            $written++;
        }

        return $written;
    }

    /**
     * @param  Collection<int, CheckResult>  $results
     */
    private function upsert(string $monitorId, ?string $probeId, Carbon $periodStart, $results): void
    {
        $up = $results->where('success', true)->count();
        $down = $results->where('success', false)->count();
        $avgLatency = $results->avg('latency_ms');

        CheckAggregate::query()->updateOrCreate(
            [
                'monitor_id' => $monitorId,
                'probe_id' => $probeId,
                'period_start' => $periodStart,
                'granularity' => AggregateGranularity::Hour,
            ],
            [
                'up_count' => $up,
                'down_count' => $down,
                'avg_latency_ms' => $avgLatency === null ? null : (int) round((float) $avgLatency),
            ],
        );
    }
}
