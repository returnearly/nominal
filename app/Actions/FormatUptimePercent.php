<?php

declare(strict_types=1);

namespace App\Actions;

use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class FormatUptimePercent implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(?float $percent): ?string
    {
        if ($percent === null) {
            return null;
        }

        return number_format($percent, 2, '.', '').'%';
    }
}
