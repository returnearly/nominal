<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Carbon;

final readonly class ReportPeriod
{
    public function __construct(
        public Carbon $startsAt,
        public Carbon $endsAt,
    ) {}

    public function seconds(): int
    {
        return (int) $this->startsAt->diffInSeconds($this->endsAt, true);
    }

    public function label(): string
    {
        $timezone = (string) config('app.timezone');

        return $this->startsAt->copy()->timezone($timezone)->format('M j, Y')
            .'–'
            .$this->endsAt->copy()->timezone($timezone)->format('M j, Y');
    }
}
