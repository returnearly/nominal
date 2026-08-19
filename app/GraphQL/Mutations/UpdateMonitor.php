<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveMonitor;
use App\Models\Monitor;

final class UpdateMonitor
{
    public function __construct(
        private readonly SaveMonitor $saveMonitor,
    ) {}

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): Monitor
    {
        $monitor = Monitor::query()->findOrFail($args['id']);
        $input = $args['input'];

        if (isset($input['requestHeaders']) && is_array($input['requestHeaders'])) {
            $headers = [];

            foreach ($input['requestHeaders'] as $pair) {
                $headers[$pair['key']] = $pair['value'];
            }

            $input['requestHeaders'] = $headers;
        }

        if (isset($input['type'])) {
            $input['type'] = strtolower((string) $input['type']);
        }

        if (isset($input['ipFamily'])) {
            $input['ipFamily'] = strtolower((string) $input['ipFamily']);
        }

        return $this->saveMonitor->handle($input, $monitor);
    }
}
