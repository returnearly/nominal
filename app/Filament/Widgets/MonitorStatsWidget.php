<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;

final class MonitorStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|array|null $columns = 4;

    protected ?string $pollingInterval = '10s';

    /**
     * @var array<string, mixed>|null
     */
    #[Reactive]
    public ?array $tableFilters = null;

    public function filterByStatus(string $status): void
    {
        $this->dispatch('filter-monitors-by-status', status: $status);
    }

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
            $this->stat(MonitorStatus::Up, $counts),
            $this->stat(MonitorStatus::Down, $counts),
            $this->stat(MonitorStatus::Pending, $counts),
            $this->stat(MonitorStatus::Paused, $counts),
        ];
    }

    /**
     * @param  Collection<string, mixed>  $counts
     */
    private function stat(MonitorStatus $status, Collection $counts): Stat
    {
        $value = $status->value;

        return Stat::make($status->name, (int) $counts->get($value, 0))
            ->color($status->getColor())
            ->description(data_get($this->tableFilters, 'status.value') === $value ? 'Filtered' : null)
            ->extraAttributes([
                'class' => 'cursor-pointer nm-stat',
                'data-status' => $value,
                'role' => 'button',
                'wire:click' => "filterByStatus('{$value}')",
            ]);
    }
}
