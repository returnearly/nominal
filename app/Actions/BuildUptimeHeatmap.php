<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class BuildUptimeHeatmap implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @return list<array{start: Carbon, up: int, down: int, avg_latency_ms: int|null}>
     */
    public function handle(Monitor $monitor, int $days = 7): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $buckets = $this->hourlyBuckets($monitor, $start);
        $cells = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $start->copy()->addDays($day);

            for ($hour = 0; $hour < 24; $hour++) {
                $period = $date->copy()->setHour($hour)->startOfHour();
                $cells[] = $this->cell($period, $buckets->get($period->toDateTimeString(), collect()));
            }
        }

        return $cells;
    }

    /**
     * @return Collection<string, Collection<int, CheckResult>>
     */
    private function hourlyBuckets(Monitor $monitor, Carbon $start): Collection
    {
        return CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $start)
            ->get(['success', 'latency_ms', 'checked_at'])
            ->groupBy(
                fn (CheckResult $result): string => $result->checked_at->copy()->startOfHour()->toDateTimeString(),
            );
    }

    /**
     * @param  Collection<int, CheckResult>  $bucket
     * @return array{start: Carbon, up: int, down: int, avg_latency_ms: int|null}
     */
    private function cell(Carbon $period, Collection $bucket): array
    {
        $average = $bucket->avg('latency_ms');

        return [
            'start' => $period,
            'up' => $bucket->where('success', true)->count(),
            'down' => $bucket->where('success', false)->count(),
            'avg_latency_ms' => $average === null ? null : (int) round((float) $average),
        ];
    }
}
