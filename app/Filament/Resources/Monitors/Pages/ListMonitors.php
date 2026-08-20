<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Actions\LoadRecentCheckResults;
use App\Enums\MonitorStatus;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Filament\Widgets\MonitorStatsWidget;
use App\Models\Monitor;
use App\Support\DownMonitorFavicon;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

final class ListMonitors extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = MonitorResource::class;

    public string $faviconHref = '';

    public int $downCount = 0;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<class-string<MonitorStatsWidget>>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            MonitorStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function booted(): void
    {
        $this->downCount = Monitor::query()->where('status', MonitorStatus::Down)->count();
        $this->faviconHref = DownMonitorFavicon::href($this->downCount) ?? asset('favicon.svg');
    }

    public function getFooter(): ?View
    {
        return view('filament.monitors.favicon');
    }

    #[On('filter-monitors-by-status')]
    public function filterByStatus(string $status): void
    {
        $filters = $this->tableFilters ?? [];
        $current = $filters['status']['value'] ?? null;

        $this->tableFilters = [
            ...$filters,
            'status' => [
                'value' => $current === $status ? null : $status,
            ],
        ];

        $this->updatedTableFilters();
    }

    protected function paginateTableQuery(Builder $query): Paginator|CursorPaginator
    {
        $records = parent::paginateTableQuery($query);
        $monitors = $records->getCollection();
        $heartbeats = LoadRecentCheckResults::make()->handle($monitors->modelKeys());

        $monitors->each(function (Monitor $monitor) use ($heartbeats): void {
            $monitor->setRelation('recentChecks', $heartbeats->get($monitor->id, collect()));
        });

        return $records;
    }
}
