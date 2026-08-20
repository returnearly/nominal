<?php

declare(strict_types=1);

namespace App\Metrics;

use App\Checking\ProbeResult;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Support\Facades\Cache;

final class MetricsStore
{
    public function record(Monitor $monitor, ?Probe $probe, ProbeResult $result): void
    {
        $labels = [
            'monitor' => $monitor->name,
            'type' => $monitor->type->value,
            'success' => $result->success ? 'true' : 'false',
            'region' => $probe?->slug ?? 'web',
        ];

        $this->increment('nominal_results_total', $labels);
        $this->gauge('nominal_monitor_up', [
            'monitor' => $monitor->name,
            'type' => $monitor->type->value,
            'region' => $probe?->slug ?? 'web',
        ], $result->success ? 1 : 0);

        if ($result->latencyMs !== null) {
            $this->gauge('nominal_check_latency_ms', [
                'monitor' => $monitor->name,
                'type' => $monitor->type->value,
                'region' => $probe?->slug ?? 'web',
            ], $result->latencyMs);
        }
    }

    /**
     * @param  array<string, string>  $labels
     */
    public function increment(string $name, array $labels, int $by = 1): void
    {
        $key = $this->seriesKey('counter', $name, $labels);
        Cache::add($key, 0);
        Cache::increment($key, $by);
        $this->index($key, 'counter', $name, $labels);
    }

    /**
     * @param  array<string, string>  $labels
     */
    public function gauge(string $name, array $labels, int|float $value): void
    {
        $key = $this->seriesKey('gauge', $name, $labels);
        Cache::forever($key, $value);
        $this->index($key, 'gauge', $name, $labels);
    }

    /**
     * @return list<array{type: string, name: string, labels: array<string, string>, value: int|float}>
     */
    public function all(): array
    {
        /** @var array<string, array{type: string, name: string, labels: array<string, string>}> $index */
        $index = Cache::get($this->indexKey(), []);
        $series = [];

        foreach ($index as $key => $meta) {
            $value = Cache::get($key);

            if ($value === null) {
                continue;
            }

            $series[] = [
                'type' => $meta['type'],
                'name' => $meta['name'],
                'labels' => $meta['labels'],
                'value' => is_numeric($value) ? $value + 0 : 0,
            ];
        }

        return $series;
    }

    public function renderPrometheus(): string
    {
        $grouped = [];

        foreach ($this->all() as $series) {
            $grouped[$series['name']][] = $series;
        }

        ksort($grouped);

        $lines = [];

        foreach ($grouped as $name => $items) {
            $type = $items[0]['type'];
            $lines[] = "# HELP {$name} Nominal {$type}.";
            $lines[] = "# TYPE {$name} {$type}";

            foreach ($items as $item) {
                $lines[] = sprintf('%s%s %s', $name, $this->formatLabels($item['labels']), $this->formatValue($item['value']));
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function seriesKey(string $type, string $name, array $labels): string
    {
        ksort($labels);

        return 'nominal:metrics:'.$type.':'.$name.':'.md5((string) json_encode($labels));
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function index(string $key, string $type, string $name, array $labels): void
    {
        /** @var array<string, array{type: string, name: string, labels: array<string, string>}> $index */
        $index = Cache::get($this->indexKey(), []);
        $index[$key] = [
            'type' => $type,
            'name' => $name,
            'labels' => $labels,
        ];
        Cache::forever($this->indexKey(), $index);
    }

    private function indexKey(): string
    {
        return 'nominal:metrics:index';
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function formatLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        ksort($labels);

        $parts = [];

        foreach ($labels as $name => $value) {
            $parts[] = sprintf('%s="%s"', $name, addcslashes($value, '\\"'));
        }

        return '{'.implode(',', $parts).'}';
    }

    private function formatValue(int|float $value): string
    {
        return is_int($value) ? (string) $value : rtrim(rtrim(sprintf('%.6f', $value), '0'), '.');
    }
}
