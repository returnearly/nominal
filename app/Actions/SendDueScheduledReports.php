<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ScheduledReport;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SendDueScheduledReports implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private SendScheduledReport $send,
    ) {}

    public function handle(): int
    {
        $due = ScheduledReport::query()
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->get();

        foreach ($due as $report) {
            $this->send->handle($report);
        }

        return $due->count();
    }
}
