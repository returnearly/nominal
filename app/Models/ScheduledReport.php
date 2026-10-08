<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScheduledReportCadence;
use App\Enums\ScheduledReportScope;
use App\Enums\ScheduledReportType;
use App\Support\MonitorTags;
use App\Support\ReportRecipients;
use Database\Factories\ScheduledReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'enabled',
    'type',
    'cadence',
    'send_time',
    'weekday',
    'day_of_month',
    'scope',
    'tags',
    'recipients',
    'next_run_at',
    'last_sent_at',
    'last_error',
])]
class ScheduledReport extends Model
{
    /** @use HasFactory<ScheduledReportFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'type' => ScheduledReportType::class,
            'cadence' => ScheduledReportCadence::class,
            'scope' => ScheduledReportScope::class,
            'weekday' => 'integer',
            'day_of_month' => 'integer',
            'next_run_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<list<string>, list<string>|string>
     */
    protected function tags(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                if (is_string($value) && $value !== '') {
                    $value = json_decode($value, true);
                }

                return MonitorTags::normalize($value);
            },
            set: fn (mixed $value): string => json_encode(MonitorTags::normalize($value)),
        );
    }

    /**
     * @return Attribute<list<string>, list<string>|string>
     */
    protected function recipients(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                if (is_string($value) && $value !== '') {
                    $value = json_decode($value, true);
                }

                return ReportRecipients::normalize($value);
            },
            set: fn (mixed $value): string => json_encode(ReportRecipients::normalize($value)),
        );
    }

    /**
     * @return list<string>
     */
    public function graphqlTags(): array
    {
        return $this->tags;
    }

    /**
     * @return list<string>
     */
    public function graphqlRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * @return BelongsToMany<Monitor, $this>
     */
    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'scheduled_report_monitor');
    }

    public function scheduleLabel(): string
    {
        $time = $this->send_time;

        return match ($this->cadence) {
            ScheduledReportCadence::Daily => 'Daily at '.$time,
            ScheduledReportCadence::Weekly => 'Weekly on '.$this->weekdayName().' at '.$time,
            ScheduledReportCadence::Monthly => 'Monthly on day '.$this->day_of_month.' at '.$time,
        };
    }

    public function weekdayName(): string
    {
        return match ($this->weekday) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => 'Monday',
        };
    }
}
