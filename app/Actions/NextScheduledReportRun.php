<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ScheduledReportCadence;
use App\Models\ScheduledReport;
use Illuminate\Support\Carbon;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class NextScheduledReportRun implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(ScheduledReport $report, ?Carbon $after = null): Carbon
    {
        $after = ($after ?? now())->copy()->timezone((string) config('app.timezone'));

        return match ($report->cadence) {
            ScheduledReportCadence::Daily => $this->daily($after, $report->send_time),
            ScheduledReportCadence::Weekly => $this->weekly($after, $report->send_time, $report->weekday ?? 1),
            ScheduledReportCadence::Monthly => $this->monthly($after, $report->send_time, $report->day_of_month ?? 1),
        };
    }

    private function daily(Carbon $after, string $sendTime): Carbon
    {
        $candidate = $this->atTime($after, $sendTime);

        if ($candidate->lessThanOrEqualTo($after)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    private function weekly(Carbon $after, string $sendTime, int $weekday): Carbon
    {
        $weekday = max(1, min(7, $weekday));
        $candidate = $this->atTime($after->copy()->startOfDay(), $sendTime);
        $delta = ($weekday - $candidate->dayOfWeekIso + 7) % 7;
        $candidate->addDays($delta);

        if ($candidate->lessThanOrEqualTo($after)) {
            $candidate->addWeek();
        }

        return $candidate;
    }

    private function monthly(Carbon $after, string $sendTime, int $day): Carbon
    {
        $day = max(1, min(28, $day));
        $candidate = $this->atTime($after->copy()->startOfMonth()->day($day), $sendTime);

        if ($candidate->lessThanOrEqualTo($after)) {
            $candidate = $this->atTime($after->copy()->startOfMonth()->addMonth()->day($day), $sendTime);
        }

        return $candidate;
    }

    private function atTime(Carbon $day, string $sendTime): Carbon
    {
        [$hour, $minute] = array_pad(explode(':', $sendTime), 2, '0');

        return $day->copy()->setTime((int) $hour, (int) $minute, 0);
    }
}
