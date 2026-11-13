<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SendScheduledReport as SendScheduledReportAction;
use App\Models\ScheduledReport;

final class SendScheduledReport
{
    public function __construct(
        private readonly SendScheduledReportAction $sendScheduledReport,
    ) {}

    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): ScheduledReport
    {
        $report = ScheduledReport::query()->findOrFail($args['id']);

        return $this->sendScheduledReport->handle($report, advanceSchedule: false);
    }
}
