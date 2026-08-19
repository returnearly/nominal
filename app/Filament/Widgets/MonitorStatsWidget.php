<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class MonitorStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $counts = Monitor::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            Stat::make('Up', (int) ($counts[MonitorStatus::Up->value] ?? $counts[MonitorStatus::Up->name] ?? 0))
                ->color('success'),
            Stat::make('Down', (int) ($counts[MonitorStatus::Down->value] ?? 0))
                ->color('danger'),
            Stat::make('Pending', (int) ($counts[MonitorStatus::Pending->value] ?? 0))
                ->color('gray'),
            Stat::make('Paused', (int) ($counts[MonitorStatus::Paused->value] ?? 0))
                ->color('warning'),
        ];
    }
}
