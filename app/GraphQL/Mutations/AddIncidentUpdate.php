<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\AddIncidentUpdate as AddIncidentUpdateAction;
use App\Models\Incident;

final class AddIncidentUpdate
{
    public function __construct(
        private readonly AddIncidentUpdateAction $addIncidentUpdate,
    ) {}

    /**
     * @param  array{incidentId: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): Incident
    {
        $incident = Incident::query()->findOrFail($args['incidentId']);

        $this->addIncidentUpdate->handle($incident, $args['input']);

        return $incident->fresh(['updates', 'monitors', 'statusPage']) ?? $incident;
    }
}
