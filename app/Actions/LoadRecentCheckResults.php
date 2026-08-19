<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CheckResult;
use Illuminate\Support\Collection;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class LoadRecentCheckResults implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  iterable<int, string>  $monitorIds
     * @return Collection<string, Collection<int, CheckResult>>
     */
    public function handle(iterable $monitorIds, int $limit = 20): Collection
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
     * @param  Collection<int, string>  $ids
     * @return Collection<int, CheckResult>
     */
    private function recentChecks(Collection $ids, int $limit): Collection
    {
        $ranked = CheckResult::query()
            ->select('check_results.*')
            ->selectRaw('row_number() over (partition by monitor_id order by checked_at desc, id desc) as heartbeat_rank')
            ->whereIn('monitor_id', $ids);

        return CheckResult::query()
            ->fromSub($ranked, 'check_results')
            ->with('probe')
            ->where('heartbeat_rank', '<=', $limit)
            ->orderBy('checked_at')
            ->orderBy('id')
            ->get();
    }
}
