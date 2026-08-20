<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Incident;

final class DeleteIncident
{
    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        $incident = Incident::query()->findOrFail($args['id']);

        return (bool) $incident->delete();
    }
}
