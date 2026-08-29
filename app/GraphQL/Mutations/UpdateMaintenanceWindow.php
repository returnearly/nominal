<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveMaintenanceWindow;
use App\Models\MaintenanceWindow;

final class UpdateMaintenanceWindow
{
    public function __construct(
        private readonly SaveMaintenanceWindow $saveMaintenanceWindow,
    ) {}

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): MaintenanceWindow
    {
        $window = MaintenanceWindow::query()->findOrFail($args['id']);

        return $this->saveMaintenanceWindow->handle($args['input'], $window);
    }
}
