<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveMonitor;
use App\Models\Monitor;

final class CreateMonitor
{
    public function __construct(
        private readonly SaveMonitor $saveMonitor,
    ) {}

    /**
     * @param  array{input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): Monitor
    {
        return $this->saveMonitor->handle($this->normalize($args['input']));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
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

        return $input;
    }
}
