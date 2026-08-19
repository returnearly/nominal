<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\BuildUptimeHeatmap;
use App\Actions\LoadRecentCheckResults;
use App\Models\CheckResult;
use App\Models\Monitor;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

final class MonitorHistoryWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.monitor-history';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '10s';

    public Monitor $record;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'checks' => $this->recentChecks(),
            'cells' => BuildUptimeHeatmap::make()->handle($this->record),
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
}
