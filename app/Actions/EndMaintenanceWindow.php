<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\MaintenanceWindow;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class EndMaintenanceWindow implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(MaintenanceWindow $window): MaintenanceWindow
    {
        if ($window->cancelled_at !== null) {
            return $window;
        }

        if ($window->isScheduled()) {
            $window->cancelled_at = now();
        } elseif ($window->isActive()) {
            $window->ends_at = now();
        }

        $window->save();

        return $window->fresh(['monitors']) ?? $window;
    }
}
