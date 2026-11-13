<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveScheduledReport;
use App\Models\ScheduledReport;

final class UpdateScheduledReport
{
    public function __construct(
        private readonly SaveScheduledReport $saveScheduledReport,
    ) {}

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): ScheduledReport
    {
        $report = ScheduledReport::query()->findOrFail($args['id']);

        return $this->saveScheduledReport->handle($args['input'], $report);
    }
}
