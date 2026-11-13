<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CheckResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class LoadRecentCheckResults implements ActionsPatternInterface
{
    use ActionsPattern;

    public const LIMIT = 40;

    /**
     * Stay under SQLite's compound-select cap when a status page passes every monitor.
     */
    private const CHUNK = 50;

    /**
     * @param  iterable<int, string>  $monitorIds
     * @return Collection<string, Collection<int, CheckResult>>
     */
    public function handle(iterable $monitorIds, int $limit = self::LIMIT): Collection
    {
        $ids = collect($monitorIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $grouped = $this->recentChecks($ids, $limit)->groupBy('monitor_id');

        return $ids->mapWithKeys(fn (string $id): array => [
            $id => $grouped->get($id, collect())->values(),
        ]);
    }

    /**
     * Latest $limit checks per monitor, oldest first.
     *
     * Each monitor is its own indexed LIMIT. A window function would rank every
     * retained row for those monitors before discarding all but $limit.
     *
     * @param  Collection<int, string>  $ids
     * @return EloquentCollection<int, CheckResult>
     */
    private function recentChecks(Collection $ids, int $limit): EloquentCollection
    {
        $results = new EloquentCollection;

        foreach ($ids->chunk(self::CHUNK) as $chunk) {
            $results = $results->concat($this->limitedChecks($chunk->values(), $limit));
        }

        $results->load('probe');

        return $results;
    }

    /**
     * @param  Collection<int, string>  $ids
     * @return EloquentCollection<int, CheckResult>
     */
    private function limitedChecks(Collection $ids, int $limit): EloquentCollection
    {
        $union = null;

        foreach ($ids as $id) {
            $branch = $this->limitedMonitorChecks((string) $id, $limit);
            $union = $union === null ? $branch : $union->unionAll($branch);
        }

        return CheckResult::query()
            ->fromSub($union, 'check_results')
            ->orderBy('checked_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<CheckResult>
     */
    private function limitedMonitorChecks(string $monitorId, int $limit): Builder
    {
        $latest = CheckResult::query()
            ->where('monitor_id', $monitorId)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit($limit);

        return CheckResult::query()->fromSub($latest, 'recent');
    }
}
