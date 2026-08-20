<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AggregateGranularity;
use App\Enums\MonitorStatus;
use App\Models\CheckAggregate;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Support\BadgePeriod;
use App\Support\MonitorBadge;
use InvalidArgumentException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class BuildMonitorBadge implements ActionsPatternInterface
{
    use ActionsPattern;

    /** @var list<int> */
    private const LATENCY_THRESHOLDS_MS = [50, 200, 300, 500, 750];

    public function __construct(
        private FormatMilliseconds $formatMilliseconds,
    ) {}

    public function handle(Monitor $monitor, string $kind, ?string $period = null): MonitorBadge
    {
        return match ($kind) {
            'status' => $this->status($monitor),
            'uptime' => $this->uptime($monitor, BadgePeriod::parse($period)),
            'latency' => $this->latency($monitor, BadgePeriod::parse($period)),
            default => throw new InvalidArgumentException('Unknown badge kind.'),
        };
    }

    private function status(Monitor $monitor): MonitorBadge
    {
        if (! $monitor->enabled) {
            return new MonitorBadge('status', 'disabled', '#9f9f9f', 'lightgrey', [
                'status' => 'disabled',
            ]);
        }

        return match ($monitor->status) {
            MonitorStatus::Up => new MonitorBadge('status', 'healthy', '#40cc11', 'brightgreen', [
                'status' => 'up',
            ]),
            MonitorStatus::Down => new MonitorBadge('status', 'unhealthy', '#c7130a', 'red', [
                'status' => 'down',
            ]),
            MonitorStatus::Pending => new MonitorBadge('status', 'pending', '#ccb311', 'yellow', [
                'status' => 'pending',
            ]),
            MonitorStatus::Paused => new MonitorBadge('status', 'paused', '#9f9f9f', 'lightgrey', [
                'status' => 'paused',
            ]),
        };
    }

    private function uptime(Monitor $monitor, BadgePeriod $period): MonitorBadge
    {
        $stats = $this->windowStats($monitor, $period);
        $label = 'uptime '.$period->key;

        if ($stats['total'] === 0) {
            return new MonitorBadge($label, 'n/a', '#9f9f9f', 'lightgrey', [
                'uptime' => null,
                'period' => $period->key,
                'samples' => 0,
            ]);
        }

        $ratio = $stats['up'] / $stats['total'];

        return new MonitorBadge(
            $label,
            $this->formatPercent($ratio),
            $this->uptimeHex($ratio),
            $this->namedColor($ratio, 0.975, 0.95, 0.9, 0.8, 0.65),
            [
                'uptime' => round($ratio, 6),
                'period' => $period->key,
                'samples' => $stats['total'],
            ],
        );
    }

    private function latency(Monitor $monitor, BadgePeriod $period): MonitorBadge
    {
        $stats = $this->windowStats($monitor, $period);
        $label = 'latency '.$period->key;

        if ($stats['latency_weight'] === 0) {
            return new MonitorBadge($label, 'n/a', '#9f9f9f', 'lightgrey', [
                'latency_ms' => null,
                'period' => $period->key,
            ]);
        }

        $average = (int) round($stats['latency_sum'] / $stats['latency_weight']);

        return new MonitorBadge(
            $label,
            $this->formatMilliseconds->handle($average) ?? 'n/a',
            $this->latencyHex($average),
            $this->latencyNamedColor($average),
            [
                'latency_ms' => $average,
                'period' => $period->key,
            ],
        );
    }

    /**
     * @return array{up: int, total: int, latency_sum: float, latency_weight: int}
     */
    private function windowStats(Monitor $monitor, BadgePeriod $period): array
    {
        $from = now()->subSeconds($period->seconds);
        $up = 0;
        $total = 0;
        $latencySum = 0.0;
        $latencyWeight = 0;
        $resultsFrom = $from;

        if ($period->usesAggregates()) {
            $hourStart = now()->copy()->startOfHour();
            $aggregates = CheckAggregate::query()
                ->where('monitor_id', $monitor->id)
                ->whereNull('probe_id')
                ->where('granularity', AggregateGranularity::Hour)
                ->where('period_start', '>=', $from->copy()->startOfHour())
                ->where('period_start', '<', $hourStart)
                ->get();

            foreach ($aggregates as $aggregate) {
                $count = $aggregate->up_count + $aggregate->down_count;
                $up += $aggregate->up_count;
                $total += $count;

                if ($aggregate->avg_latency_ms !== null && $count > 0) {
                    $latencySum += $aggregate->avg_latency_ms * $count;
                    $latencyWeight += $count;
                }
            }

            if ($aggregates->isNotEmpty()) {
                $resultsFrom = $hourStart;
            }
        }

        $results = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $resultsFrom)
            ->get(['success', 'latency_ms']);

        foreach ($results as $result) {
            $total++;

            if ($result->success) {
                $up++;
            }

            if ($result->latency_ms !== null) {
                $latencySum += $result->latency_ms;
                $latencyWeight++;
            }
        }

        return [
            'up' => $up,
            'total' => $total,
            'latency_sum' => $latencySum,
            'latency_weight' => $latencyWeight,
        ];
    }

    private function formatPercent(float $ratio): string
    {
        return rtrim(rtrim(number_format($ratio * 100, 2, '.', ''), '0'), '.').'%';
    }

    private function uptimeHex(float $ratio): string
    {
        return match (true) {
            $ratio >= 0.975 => '#40cc11',
            $ratio >= 0.95 => '#94cc11',
            $ratio >= 0.9 => '#ccd311',
            $ratio >= 0.8 => '#ccb311',
            $ratio >= 0.65 => '#cc8111',
            default => '#c7130a',
        };
    }

    private function latencyHex(int $milliseconds): string
    {
        return match (true) {
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[0] => '#40cc11',
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[1] => '#94cc11',
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[2] => '#ccd311',
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[3] => '#ccb311',
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[4] => '#cc8111',
            default => '#c7130a',
        };
    }

    private function namedColor(float $value, float $awesome, float $great, float $good, float $passable, float $bad): string
    {
        return match (true) {
            $value >= $awesome => 'brightgreen',
            $value >= $great => 'green',
            $value >= $good => 'yellowgreen',
            $value >= $passable => 'yellow',
            $value >= $bad => 'orange',
            default => 'red',
        };
    }

    private function latencyNamedColor(int $milliseconds): string
    {
        return match (true) {
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[0] => 'brightgreen',
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[1] => 'green',
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[2] => 'yellowgreen',
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[3] => 'yellow',
            $milliseconds <= self::LATENCY_THRESHOLDS_MS[4] => 'orange',
            default => 'red',
        };
    }
}
