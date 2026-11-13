<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScheduledReportCadence;
use App\Enums\ScheduledReportScope;
use App\Enums\ScheduledReportType;
use App\Models\Monitor;
use App\Models\ScheduledReport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends Factory<ScheduledReport>
 */
class ScheduledReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Weekly uptime',
            'enabled' => true,
            'type' => ScheduledReportType::Uptime,
            'cadence' => ScheduledReportCadence::Weekly,
            'send_time' => '08:00',
            'weekday' => 1,
            'day_of_month' => 1,
            'scope' => ScheduledReportScope::All,
            'tags' => [],
            'recipients' => ['ops@example.com'],
            'next_run_at' => null,
            'last_sent_at' => null,
            'last_error' => null,
        ];
    }

    /**
     * @param  iterable<Monitor|string>  $monitors
     */
    public function withMonitors(iterable $monitors): static
    {
        return $this->afterCreating(function (ScheduledReport $report) use ($monitors): void {
            $ids = Collection::make($monitors)
                ->map(fn (Monitor|string $monitor): string => $monitor instanceof Monitor ? $monitor->id : $monitor)
                ->all();

            $report->monitors()->sync($ids);
        });
    }
}
