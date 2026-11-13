<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AggregateGranularity;
use App\Models\CheckAggregate;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\ScheduledReport;
use App\Reports\UptimeReportRow;
use App\Reports\UptimeReportSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ComputeUptimeReport implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private ResolveScheduledReportMonitors $monitors,
        private ResolveScheduledReportPeriod $period,
    ) {}

    public function handle(ScheduledReport $report, ?Carbon $at = null): UptimeReportSummary
    {
        $at = ($at ?? now())->copy();
        $window = $this->period->handle($report, $at);
        $monitors = $this->monitors->handle($report);
        $seconds = $window->seconds();

        if ($monitors->isEmpty()) {
            return new UptimeReportSummary(
                startsAt: $window->startsAt,
                endsAt: $window->endsAt,
                uptimePercent: null,
                downtimeSeconds: null,
                outages: 0,
                note: null,
                rows: [],
            );
        }

        /** @var list<string> $ids */
        $ids = $monitors->pluck('id')->all();
        $days = $this->days($window->startsAt, $window->endsAt);
        $today = $at->copy()->timezone((string) config('app.timezone'))->startOfDay();
        $aggregates = $this->aggregates($ids, $window->startsAt, $window->endsAt);
        $results = $this->results($ids, $window->startsAt, $window->endsAt);
        $previous = $this->previousSuccess($ids, $window->startsAt);

        $rows = [];
        $up = 0;
        $down = 0;
        $outages = 0;

        foreach ($monitors as $monitor) {
            $counts = $this->counts(
                $aggregates->get($monitor->id, collect()),
                $results->get($monitor->id, collect()),
                $days,
                $today,
            );
            $monitorOutages = $this->outages(
                $results->get($monitor->id, collect()),
                $previous->get($monitor->id),
            );
            $outages += $monitorOutages;

            if ($counts['total'] > 0) {
                $up += $counts['up'];
                $down += $counts['down'];
            }

            $rows[] = new UptimeReportRow(
                name: $monitor->name,
                uptimePercent: $this->percent($counts['up'], $counts['down']),
                downtimeSeconds: $this->downtime($counts['up'], $counts['down'], $seconds),
                outages: $monitorOutages,
            );
        }

        usort($rows, function (UptimeReportRow $left, UptimeReportRow $right): int {
            if ($left->uptimePercent === null && $right->uptimePercent === null) {
                return strnatcasecmp($left->name, $right->name);
            }

            if ($left->uptimePercent === null) {
                return 1;
            }

            if ($right->uptimePercent === null) {
                return -1;
            }

            $byUptime = $left->uptimePercent <=> $right->uptimePercent;

            return $byUptime !== 0 ? $byUptime : strnatcasecmp($left->name, $right->name);
        });

        return new UptimeReportSummary(
            startsAt: $window->startsAt,
            endsAt: $window->endsAt,
            uptimePercent: $this->percent($up, $down),
            downtimeSeconds: $this->downtime($up, $down, $seconds),
            outages: $outages,
            note: $this->note($monitors, $window->startsAt, $at),
            rows: $rows,
        );
    }

    /**
     * @return list<Carbon>
     */
    private function days(Carbon $startsAt, Carbon $endsAt): array
    {
        $days = [];
        $cursor = $startsAt->copy()->timezone((string) config('app.timezone'))->startOfDay();

        while ($cursor->lt($endsAt)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<string, Collection<int, CheckAggregate>>
     */
    private function aggregates(array $ids, Carbon $startsAt, Carbon $endsAt): Collection
    {
        return CheckAggregate::query()
            ->whereIn('monitor_id', $ids)
            ->whereNull('probe_id')
            ->where('granularity', AggregateGranularity::Day)
            ->where('period_start', '>=', $startsAt)
            ->where('period_start', '<', $endsAt)
            ->get()
            ->groupBy('monitor_id');
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<string, Collection<int, CheckResult>>
     */
    private function results(array $ids, Carbon $startsAt, Carbon $endsAt): Collection
    {
        return CheckResult::query()
            ->whereIn('monitor_id', $ids)
            ->where('checked_at', '>=', $startsAt)
            ->where('checked_at', '<', $endsAt)
            ->orderBy('checked_at')
            ->orderBy('id')
            ->get(['id', 'monitor_id', 'checked_at', 'success'])
            ->groupBy('monitor_id');
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<string, bool>
     */
    private function previousSuccess(array $ids, Carbon $startsAt): Collection
    {
        return CheckResult::query()
            ->whereIn('monitor_id', $ids)
            ->where('checked_at', '<', $startsAt)
            ->whereRaw(
                'checked_at = (select max(latest.checked_at) from check_results as latest where latest.monitor_id = check_results.monitor_id and latest.checked_at < ?)',
                [$startsAt],
            )
            ->orderByDesc('id')
            ->get(['id', 'monitor_id', 'success'])
            ->unique('monitor_id')
            ->mapWithKeys(fn (CheckResult $result): array => [$result->monitor_id => (bool) $result->success]);
    }

    /**
     * @param  Collection<int, CheckAggregate>  $aggregates
     * @param  Collection<int, CheckResult>  $results
     * @param  list<Carbon>  $days
     * @return array{up: int, down: int, total: int}
     */
    private function counts(Collection $aggregates, Collection $results, array $days, Carbon $today): array
    {
        $up = 0;
        $down = 0;

        foreach ($days as $day) {
            $aggregate = $aggregates->first(
                fn (CheckAggregate $row): bool => $row->period_start->copy()->timezone((string) config('app.timezone'))->startOfDay()->equalTo($day),
            );

            if ($aggregate !== null && ! $day->equalTo($today)) {
                $up += $aggregate->up_count;
                $down += $aggregate->down_count;

                continue;
            }

            $dayEnd = $day->copy()->addDay();
            $slice = $results->filter(
                fn (CheckResult $result): bool => $result->checked_at->gte($day) && $result->checked_at->lt($dayEnd),
            );
            $up += $slice->where('success', true)->count();
            $down += $slice->where('success', false)->count();
        }

        return ['up' => $up, 'down' => $down, 'total' => $up + $down];
    }

    /**
     * @param  Collection<int, CheckResult>  $results
     */
    private function outages(Collection $results, mixed $previousSuccess): int
    {
        $previous = is_bool($previousSuccess) ? $previousSuccess : null;
        $outages = 0;

        foreach ($results as $result) {
            $success = (bool) $result->success;

            if (! $success && $previous === true) {
                $outages++;
            }

            $previous = $success;
        }

        return $outages;
    }

    private function percent(int $up, int $down): ?float
    {
        $total = $up + $down;

        if ($total === 0) {
            return null;
        }

        return round(100 * $up / $total, 4);
    }

    private function downtime(int $up, int $down, int $seconds): ?int
    {
        $total = $up + $down;

        if ($total === 0) {
            return null;
        }

        return (int) round($down / $total * $seconds);
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     */
    private function note(Collection $monitors, Carbon $startsAt, Carbon $at): ?string
    {
        $cutoff = null;

        foreach ($monitors as $monitor) {
            $monitorCutoff = $at->copy()->subDays($monitor->retention_days);

            if ($monitorCutoff->gt($startsAt) && ($cutoff === null || $monitorCutoff->gt($cutoff))) {
                $cutoff = $monitorCutoff;
            }
        }

        if ($cutoff === null) {
            return null;
        }

        return 'Data starts '.$cutoff->timezone((string) config('app.timezone'))->format('M j, Y').'; earlier checks were pruned.';
    }
}
