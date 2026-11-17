<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ScheduledReportScope;
use App\Models\Monitor;
use App\Models\ScheduledReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ResolveScheduledReportMonitors implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @return Collection<int, Monitor>
     */
    public function handle(ScheduledReport $report): Collection
    {
        $query = Monitor::query()->orderBy('name');

        return match ($report->scope) {
            ScheduledReportScope::All => $query->get(),
            ScheduledReportScope::Monitors => $query->whereKey($report->monitors()->pluck('monitors.id'))->get(),
            ScheduledReportScope::Tags => $this->tagged($query, $report->tags),
        };
    }

    /**
     * @param  Builder<Monitor>  $query
     * @param  list<string>  $tags
     * @return Collection<int, Monitor>
     */
    private function tagged(Builder $query, array $tags): Collection
    {
        if ($tags === []) {
            return collect();
        }

        return $query->where(function (Builder $query) use ($tags): void {
            foreach ($tags as $tag) {
                $query->orWhere(fn (Builder $query) => $query->tagged($tag));
            }
        })->get();
    }
}
