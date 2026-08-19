<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

final class MonitorStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = 4;

    protected ?string $pollingInterval = '10s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $counts = Monitor::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            Stat::make('Up', $this->count($counts, MonitorStatus::Up))
                ->color('success'),
            Stat::make('Down', $this->count($counts, MonitorStatus::Down))
                ->color('danger'),
            Stat::make('Pending', $this->count($counts, MonitorStatus::Pending))
                ->color('gray'),
            Stat::make('Paused', $this->count($counts, MonitorStatus::Paused))
                ->color('warning'),
        ];
    }

    /**
     * @param  Collection<array-key, mixed>  $counts
     */
    private function count(Collection $counts, MonitorStatus $status): int
    {
        return (int) $counts->get($status->value, 0);
    }
}
