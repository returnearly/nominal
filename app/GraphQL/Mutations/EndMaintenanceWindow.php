<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\EndMaintenanceWindow as EndMaintenanceWindowAction;
use App\Models\MaintenanceWindow;

final class EndMaintenanceWindow
{
    public function __construct(
        private readonly EndMaintenanceWindowAction $endMaintenanceWindow,
    ) {}

    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): MaintenanceWindow
    {
        $window = MaintenanceWindow::query()->findOrFail($args['id']);

        return $this->endMaintenanceWindow->handle($window);
    }
}
