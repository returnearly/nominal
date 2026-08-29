<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveMaintenanceWindow;
use App\Models\MaintenanceWindow;

final class CreateMaintenanceWindow
{
    public function __construct(
        private readonly SaveMaintenanceWindow $saveMaintenanceWindow,
    ) {}

    /**
     * @param  array{input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): MaintenanceWindow
    {
        return $this->saveMaintenanceWindow->handle($args['input']);
    }
}
