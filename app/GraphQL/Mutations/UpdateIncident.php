<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveIncident;
use App\Models\Incident;

final class UpdateIncident
{
    public function __construct(
        private readonly SaveIncident $saveIncident,
    ) {}

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): Incident
    {
        $incident = Incident::query()->findOrFail($args['id']);

        return $this->saveIncident->handle($args['input'], $incident);
    }
}
