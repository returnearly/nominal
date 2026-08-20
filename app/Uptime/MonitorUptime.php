<?php

declare(strict_types=1);

namespace App\Uptime;

use App\Enums\UptimeWindow;

final readonly class MonitorUptime
{
    public function __construct(
        public ?float $oneHour,
        public ?float $twentyFourHours,
        public ?float $sevenDays,
        public ?float $thirtyDays,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    public function percent(UptimeWindow $window): ?float
    {
        return match ($window) {
            UptimeWindow::OneHour => $this->oneHour,
            UptimeWindow::TwentyFourHours => $this->twentyFourHours,
            UptimeWindow::SevenDays => $this->sevenDays,
            UptimeWindow::ThirtyDays => $this->thirtyDays,
        };
    }

    /**
     * @return array{oneHour: ?float, twentyFourHours: ?float, sevenDays: ?float, thirtyDays: ?float}
     */
    public function toGraphQL(): array
    {
        return [
            'oneHour' => $this->oneHour,
            'twentyFourHours' => $this->twentyFourHours,
            'sevenDays' => $this->sevenDays,
            'thirtyDays' => $this->thirtyDays,
        ];
    }
}
