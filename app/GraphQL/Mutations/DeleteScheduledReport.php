<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\ScheduledReport;

final class DeleteScheduledReport
{
    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        $report = ScheduledReport::query()->findOrFail($args['id']);

        return (bool) $report->delete();
    }
}
