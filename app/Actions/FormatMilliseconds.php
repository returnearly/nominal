<?php

declare(strict_types=1);

namespace App\Actions;

use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class FormatMilliseconds implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(?int $milliseconds): ?string
    {
        if ($milliseconds === null) {
            return null;
        }

        if (abs($milliseconds) < 1000) {
            return $milliseconds.'ms';
        }

        $seconds = $milliseconds / 1000;
        $formatted = rtrim(rtrim(number_format($seconds, 2, '.', ''), '0'), '.');

        return $formatted.'s';
    }
}
