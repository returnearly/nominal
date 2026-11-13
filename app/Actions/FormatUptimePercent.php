<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UptimeWindow;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class FormatUptimePercent implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(?float $percent, ?UptimeWindow $window = null): ?string
    {
        if ($percent === null) {
            return null;
        }

        $formatted = number_format($percent, 3, '.', '').'%';

        if ($window === null) {
            return $formatted;
        }

        $offlineSeconds = (int) round((100 - $percent) / 100 * $window->hours() * 3600);

        return $formatted.' - ['.$this->formatOffline($offlineSeconds).']';
    }

    private function formatOffline(int $seconds): string
    {
        $seconds = max(0, $seconds);

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $days = intdiv($seconds, 86_400);
        $seconds %= 86_400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days.'d';
        }

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($minutes > 0) {
            $parts[] = $minutes.'m';
        }

        if ($seconds > 0 && $days === 0 && $hours === 0) {
            $parts[] = $seconds.'s';
        }

        return $parts === [] ? '0s' : implode('', $parts);
    }
}
