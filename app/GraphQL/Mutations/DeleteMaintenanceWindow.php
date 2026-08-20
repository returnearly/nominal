<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\MaintenanceWindow;

final class DeleteMaintenanceWindow
{
    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        $window = MaintenanceWindow::query()->findOrFail($args['id']);

        return (bool) $window->delete();
    }
}
