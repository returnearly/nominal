<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AggregateGranularity;
use App\Models\CheckAggregate;
use App\Models\CheckResult;
use App\Uptime\MonitorUptime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ComputeMonitorUptime implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  iterable<int, string>  $monitorIds
     * @return Collection<string, MonitorUptime>
     */
    public function handle(iterable $monitorIds): Collection
    {
        $ids = collect($monitorIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $now = now();
        $currentHour = $now->copy()->startOfHour();
        $oneHourAgo = $now->copy()->subHour();
        $dayAgo = $now->copy()->subDay();
        $weekAgo = $now->copy()->subDays(7);
        $monthAgo = $now->copy()->subDays(30);

        $raw = $this->recentCounts($ids, $oneHourAgo, $dayAgo, $currentHour);
        $hourly = $this->hourlyCounts($ids, $monthAgo->copy()->startOfHour(), $currentHour);
        $fallbackIds = $ids->reject(fn (string $id): bool => $hourly->has($id))->values();
        $fallback = $fallbackIds->isEmpty()
            ? collect()
            : $this->rawWindowCounts($fallbackIds, $weekAgo, $monthAgo);

        return $ids->mapWithKeys(function (string $id) use ($raw, $hourly, $fallback, $weekAgo, $monthAgo): array {
            $row = $raw->get($id);
            $hours = $hourly->get($id, collect());
            $currentUp = (int) ($row?->up_current_hour ?? 0);
            $currentDown = (int) ($row?->down_current_hour ?? 0);

            if ($hourly->has($id)) {
                $sevenDays = $this->percentFromHours($hours, $weekAgo->copy()->startOfHour(), $currentUp, $currentDown);
                $thirtyDays = $this->percentFromHours($hours, $monthAgo->copy()->startOfHour(), $currentUp, $currentDown);
            } else {
                $window = $fallback->get($id);
                $sevenDays = $this->percent((int) ($window?->up_7d ?? 0), (int) ($window?->down_7d ?? 0));
                $thirtyDays = $this->percent((int) ($window?->up_30d ?? 0), (int) ($window?->down_30d ?? 0));
            }

            return [$id => new MonitorUptime(
                oneHour: $this->percent((int) ($row?->up_1h ?? 0), (int) ($row?->down_1h ?? 0)),
                twentyFourHours: $this->percent((int) ($row?->up_24h ?? 0), (int) ($row?->down_24h ?? 0)),
                sevenDays: $sevenDays,
                thirtyDays: $thirtyDays,
            )];
        });
    }

    /**
     * @param  Collection<int, string>  $ids
     * @return Collection<string, object>
     */
    private function recentCounts(Collection $ids, Carbon $oneHourAgo, Carbon $dayAgo, Carbon $currentHour): Collection
    {
        return CheckResult::query()
            ->select('monitor_id')
            ->selectRaw('sum(case when checked_at >= ? and success then 1 else 0 end) as up_1h', [$oneHourAgo])
            ->selectRaw('sum(case when checked_at >= ? and not success then 1 else 0 end) as down_1h', [$oneHourAgo])
            ->selectRaw('sum(case when success then 1 else 0 end) as up_24h')
            ->selectRaw('sum(case when not success then 1 else 0 end) as down_24h')
            ->selectRaw('sum(case when checked_at >= ? and success then 1 else 0 end) as up_current_hour', [$currentHour])
            ->selectRaw('sum(case when checked_at >= ? and not success then 1 else 0 end) as down_current_hour', [$currentHour])
            ->whereIn('monitor_id', $ids)
            ->where('checked_at', '>=', $dayAgo)
            ->groupBy('monitor_id')
            ->get()
            ->keyBy('monitor_id');
    }

    /**
     * @param  Collection<int, string>  $ids
     * @return Collection<string, Collection<int, CheckAggregate>>
     */
    private function hourlyCounts(Collection $ids, Carbon $from, Carbon $currentHour): Collection
    {
        return CheckAggregate::query()
            ->whereIn('monitor_id', $ids)
            ->whereNull('probe_id')
            ->where('granularity', AggregateGranularity::Hour)
            ->where('period_start', '>=', $from)
            ->where('period_start', '<', $currentHour)
            ->get()
            ->groupBy('monitor_id');
    }

    /**
     * @param  Collection<int, string>  $ids
     * @return Collection<string, object>
     */
    private function rawWindowCounts(Collection $ids, Carbon $weekAgo, Carbon $monthAgo): Collection
    {
        return CheckResult::query()
            ->select('monitor_id')
            ->selectRaw('sum(case when checked_at >= ? and success then 1 else 0 end) as up_7d', [$weekAgo])
            ->selectRaw('sum(case when checked_at >= ? and not success then 1 else 0 end) as down_7d', [$weekAgo])
            ->selectRaw('sum(case when success then 1 else 0 end) as up_30d')
            ->selectRaw('sum(case when not success then 1 else 0 end) as down_30d')
            ->whereIn('monitor_id', $ids)
            ->where('checked_at', '>=', $monthAgo)
            ->groupBy('monitor_id')
            ->get()
            ->keyBy('monitor_id');
    }

    /**
     * @param  Collection<int, CheckAggregate>  $hours
     */
    private function percentFromHours(Collection $hours, Carbon $from, int $currentUp, int $currentDown): ?float
    {
        $slice = $hours->filter(fn (CheckAggregate $row): bool => $row->period_start->gte($from));

        return $this->percent(
            (int) $slice->sum('up_count') + $currentUp,
            (int) $slice->sum('down_count') + $currentDown,
        );
    }

    private function percent(int $up, int $down): ?float
    {
        $total = $up + $down;

        if ($total === 0) {
            return null;
        }

        return round(100 * $up / $total, 4);
    }
}
