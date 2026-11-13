<?php

declare(strict_types=1);

namespace App\Reports;

use App\Actions\FormatUptimePercent;

final readonly class UptimeReportRow
{
    public function __construct(
        public string $name,
        public ?float $uptimePercent,
        public ?int $downtimeSeconds,
        public int $outages,
    ) {}

    public function uptimeLabel(): string
    {
        if ($this->uptimePercent === null) {
            return 'no data';
        }

        return FormatUptimePercent::make()->handle($this->uptimePercent) ?? 'no data';
    }

    public function downtimeLabel(): string
    {
        if ($this->downtimeSeconds === null) {
            return 'no data';
        }

        return self::duration($this->downtimeSeconds);
    }

    public static function duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0 && $minutes > 0) {
            return $hours.'h '.$minutes.'m';
        }

        if ($hours > 0) {
            return $hours.'h';
        }

        return $minutes.'m';
    }
}
