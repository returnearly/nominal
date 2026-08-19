<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Monitor;

final class DeleteMonitor
{
    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        $monitor = Monitor::query()->findOrFail($args['id']);

        return (bool) $monitor->delete();
    }
}
