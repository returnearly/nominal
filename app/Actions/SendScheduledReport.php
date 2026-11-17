<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ScheduledReportType;
use App\Mail\ScheduledReportMail;
use App\Models\ScheduledReport;
use Illuminate\Support\Facades\Mail;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;
use Throwable;

final readonly class SendScheduledReport implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private ComputeUptimeReport $uptime,
        private NextScheduledReportRun $nextRun,
    ) {}

    public function handle(ScheduledReport $report, bool $advanceSchedule = true): ScheduledReport
    {
        try {
            $this->deliver($report);
            $report->last_sent_at = now();
            $report->last_error = null;
        } catch (Throwable $exception) {
            $report->last_error = $exception->getMessage();
        }

        if ($advanceSchedule) {
            $report->next_run_at = $this->nextRun->handle($report);
        }

        $report->save();

        return $report->fresh() ?? $report;
    }

    private function deliver(ScheduledReport $report): void
    {
        $type = (string) ($report->getAttributes()['type'] ?? '');

        if ($type !== ScheduledReportType::Uptime->value) {
            throw new \RuntimeException('Unsupported scheduled report type.');
        }

        if ($report->recipients === []) {
            throw new \RuntimeException('Add at least one recipient.');
        }

        $summary = $this->uptime->handle($report);

        Mail::to($report->recipients)->send(new ScheduledReportMail($report, $summary));
    }
}
