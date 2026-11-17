<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveScheduledReport;
use App\Models\ScheduledReport;

final class CreateScheduledReport
{
    public function __construct(
        private readonly SaveScheduledReport $saveScheduledReport,
    ) {}

    /**
     * @param  array{input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): ScheduledReport
    {
        return $this->saveScheduledReport->handle($args['input']);
    }
}
