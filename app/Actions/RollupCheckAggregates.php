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
        $dayKeys = [];

        $groups = CheckResult::query()
            ->where('checked_at', '>=', $periodStart)
            ->where('checked_at', '<', $periodEnd)
            ->get()
            ->groupBy(fn (CheckResult $result): string => $result->monitor_id.'|'.$result->probe_id);

        foreach ($groups as $results) {
            $first = $results->first();
            $this->upsertHour(
                $first->monitor_id,
                $first->probe_id,
                $periodStart,
                $results,
            );
            $dayKeys[$first->monitor_id.'|'.$first->probe_id] = [$first->monitor_id, $first->probe_id];
            $written++;
        }

        $byMonitor = CheckResult::query()
            ->where('checked_at', '>=', $periodStart)
            ->where('checked_at', '<', $periodEnd)
            ->get()
            ->groupBy('monitor_id');

        foreach ($byMonitor as $monitorId => $results) {
            $this->upsertHour((string) $monitorId, null, $periodStart, $results);
            $dayKeys[$monitorId.'|'] = [(string) $monitorId, null];
            $written++;
        }

        foreach ($dayKeys as [$monitorId, $probeId]) {
            $this->upsertDay($monitorId, $probeId, $periodStart);
            $written++;
        }

        return $written;
    }

    /**
     * @param  Collection<int, CheckResult>  $results
     */
    private function upsertHour(string $monitorId, ?string $probeId, Carbon $periodStart, $results): void
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

    private function upsertDay(string $monitorId, ?string $probeId, Carbon $hourStart): void
    {
        $dayStart = $hourStart->copy()->startOfDay();
        $hours = CheckAggregate::query()
            ->where('monitor_id', $monitorId)
            ->where('granularity', AggregateGranularity::Hour)
            ->where('period_start', '>=', $dayStart)
            ->where('period_start', '<', $dayStart->copy()->addDay())
            ->when(
                $probeId === null,
                fn ($query) => $query->whereNull('probe_id'),
                fn ($query) => $query->where('probe_id', $probeId),
            )
            ->get();

        if ($hours->isEmpty()) {
            return;
        }

        $up = (int) $hours->sum('up_count');
        $down = (int) $hours->sum('down_count');
        $weightedLatency = $hours
            ->filter(fn (CheckAggregate $hour): bool => $hour->avg_latency_ms !== null)
            ->sum(fn (CheckAggregate $hour): int => $hour->avg_latency_ms * ($hour->up_count + $hour->down_count));
        $latencySamples = (int) $hours
            ->filter(fn (CheckAggregate $hour): bool => $hour->avg_latency_ms !== null)
            ->sum(fn (CheckAggregate $hour): int => $hour->up_count + $hour->down_count);

        CheckAggregate::query()->updateOrCreate(
            [
                'monitor_id' => $monitorId,
                'probe_id' => $probeId,
                'period_start' => $dayStart,
                'granularity' => AggregateGranularity::Day,
            ],
            [
                'up_count' => $up,
                'down_count' => $down,
                'avg_latency_ms' => $latencySamples === 0 ? null : (int) round($weightedLatency / $latencySamples),
            ],
        );
    }
}
