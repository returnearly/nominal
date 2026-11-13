<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ScheduledReportCadence;
use App\Models\ScheduledReport;
use App\Reports\ReportPeriod;
use Illuminate\Support\Carbon;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ResolveScheduledReportPeriod implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(ScheduledReport $report, ?Carbon $at = null): ReportPeriod
    {
        $at = ($at ?? now())->copy()->timezone((string) config('app.timezone'));

        [$startsAt, $endsAt] = match ($report->cadence) {
            ScheduledReportCadence::Daily => $this->daily($at),
            ScheduledReportCadence::Weekly => $this->weekly($at, $report->weekday ?? 1),
            ScheduledReportCadence::Monthly => $this->monthly($at),
        };

        return new ReportPeriod($startsAt, $endsAt);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function daily(Carbon $at): array
    {
        $endsAt = $at->copy()->startOfDay();

        return [$endsAt->copy()->subDay(), $endsAt];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function weekly(Carbon $at, int $weekday): array
    {
        $weekday = max(1, min(7, $weekday));
        $endsAt = $at->copy()->startOfDay();

        for ($i = 0; $i < 7 && $endsAt->dayOfWeekIso !== $weekday; $i++) {
            $endsAt->subDay();
        }

        return [$endsAt->copy()->subWeek(), $endsAt];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthly(Carbon $at): array
    {
        $endsAt = $at->copy()->startOfMonth();

        return [$endsAt->copy()->subMonth(), $endsAt];
    }
}
