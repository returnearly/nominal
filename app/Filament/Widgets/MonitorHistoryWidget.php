<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\LoadLatencyTrend;
use App\Actions\LoadRecentCheckResults;
use App\Filament\Concerns\RefreshesOnMonitorBroadcasts;
use App\Models\CheckResult;
use App\Models\Monitor;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

final class MonitorHistoryWidget extends Widget
{
    use RefreshesOnMonitorBroadcasts;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.monitor-history';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '10s';

    public Monitor $record;

    public function placeholder(): View
    {
        return view('filament.widgets.monitor-history-placeholder');
    }

    protected function onMonitorBroadcast(): void
    {
        $this->record = $this->record->fresh() ?? $this->record;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $checks = $this->recentChecks();
        $latencyChecks = $this->latencyChecks($checks);
        $averageLatency = $latencyChecks->avg('latency_ms');
        $minLatency = $latencyChecks->min('latency_ms');
        $maxLatency = $latencyChecks->max('latency_ms');

        return [
            'checks' => $checks,
            'latencyChecks' => $latencyChecks,
            'uptime' => $this->record->uptime(),
            'statusLabel' => $this->record->enabled
                ? $this->record->effectiveStatus()->badgeLabel()
                : 'Disabled',
            'averageLatencyMs' => $averageLatency === null ? null : (int) round((float) $averageLatency),
            'minLatencyMs' => $minLatency === null ? null : (int) $minLatency,
            'maxLatencyMs' => $maxLatency === null ? null : (int) $maxLatency,
        ];
    }

    /**
     * @return Collection<int, CheckResult>
     */
    private function recentChecks(): Collection
    {
        $monitorId = $this->record->id;

        return LoadRecentCheckResults::make()
            ->handle([$monitorId])
            ->get($monitorId, collect());
    }

    /**
     * @param  Collection<int, CheckResult>  $checks
     * @return Collection<int, mixed>
     */
    private function latencyChecks(Collection $checks): Collection
    {
        $hourly = LoadLatencyTrend::make()->handle($this->record);

        return $hourly->isNotEmpty() ? $hourly : $checks;
    }
}
