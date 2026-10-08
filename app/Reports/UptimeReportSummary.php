<?php

declare(strict_types=1);

namespace App\Reports;

use App\Actions\FormatUptimePercent;
use Illuminate\Support\Carbon;

final readonly class UptimeReportSummary
{
    /**
     * @param  list<UptimeReportRow>  $rows
     */
    public function __construct(
        public Carbon $startsAt,
        public Carbon $endsAt,
        public ?float $uptimePercent,
        public ?int $downtimeSeconds,
        public int $outages,
        public ?string $note,
        public array $rows,
    ) {}

    public function periodLabel(): string
    {
        $timezone = (string) config('app.timezone');

        return $this->startsAt->copy()->timezone($timezone)->format('M j, Y')
            .'–'
            .$this->endsAt->copy()->timezone($timezone)->format('M j, Y');
    }

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

        return UptimeReportRow::duration($this->downtimeSeconds);
    }
}
