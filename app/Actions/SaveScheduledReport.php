<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ScheduledReportCadence;
use App\Enums\ScheduledReportScope;
use App\Enums\ScheduledReportType;
use App\Models\Monitor;
use App\Models\ScheduledReport;
use App\Support\EnumValue;
use App\Support\MonitorTags;
use App\Support\ReportRecipients;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SaveScheduledReport implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private NextScheduledReportRun $nextRun,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?ScheduledReport $report = null): ScheduledReport
    {
        $report ??= new ScheduledReport;
        $type = $this->type($input, $report);
        $cadence = $this->cadence($input, $report);
        $scope = $this->scope($input, $report);
        $name = trim((string) ($input['name'] ?? $report->name ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'A name is required.',
            ]);
        }

        $recipients = $this->recipients($input, $report);
        $tags = $this->tags($input, $report);
        $monitorIds = $this->monitorIds($input, $report, $scope);

        $report->fill([
            'name' => $name,
            'enabled' => (bool) ($input['enabled'] ?? $report->enabled ?? true),
            'type' => $type,
            'cadence' => $cadence,
            'send_time' => $this->sendTime($input['send_time'] ?? $input['sendTime'] ?? $report->send_time ?? '08:00'),
            'weekday' => $this->boundedInt($input['weekday'] ?? $report->weekday ?? 1, 1, 7, 'weekday'),
            'day_of_month' => $this->boundedInt($input['day_of_month'] ?? $input['dayOfMonth'] ?? $report->day_of_month ?? 1, 1, 28, 'day_of_month'),
            'scope' => $scope,
            'tags' => $tags,
            'recipients' => $recipients,
        ]);
        $report->next_run_at = $this->nextRun->handle($report);
        $report->save();

        $report->monitors()->sync($scope === ScheduledReportScope::Monitors ? $monitorIds : []);

        return $report->fresh(['monitors']) ?? $report;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function type(array $input, ScheduledReport $report): ScheduledReportType
    {
        $type = $this->enum(
            ScheduledReportType::class,
            $input['type'] ?? $report->type ?? ScheduledReportType::Uptime,
            'type',
        );

        if ($type !== ScheduledReportType::Uptime) {
            throw ValidationException::withMessages([
                'type' => 'Only uptime reports can be scheduled.',
            ]);
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function cadence(array $input, ScheduledReport $report): ScheduledReportCadence
    {
        return $this->enum(
            ScheduledReportCadence::class,
            $input['cadence'] ?? $report->cadence ?? ScheduledReportCadence::Weekly,
            'cadence',
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function scope(array $input, ScheduledReport $report): ScheduledReportScope
    {
        return $this->enum(
            ScheduledReportScope::class,
            $input['scope'] ?? $report->scope ?? ScheduledReportScope::All,
            'scope',
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function recipients(array $input, ScheduledReport $report): array
    {
        if (! array_key_exists('recipients', $input)) {
            $recipients = $report->exists ? $report->recipients : [];
        } elseif (ReportRecipients::containsInvalid($input['recipients'])) {
            throw ValidationException::withMessages([
                'recipients' => 'Enter valid email addresses.',
            ]);
        } else {
            $recipients = ReportRecipients::normalize($input['recipients']);
        }

        if ($recipients === []) {
            throw ValidationException::withMessages([
                'recipients' => 'Add at least one email address.',
            ]);
        }

        return $recipients;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function tags(array $input, ScheduledReport $report): array
    {
        if (array_key_exists('tags', $input)) {
            return MonitorTags::normalize($input['tags']);
        }

        return $report->exists ? $report->tags : [];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function monitorIds(array $input, ScheduledReport $report, ScheduledReportScope $scope): array
    {
        $tags = array_key_exists('tags', $input)
            ? MonitorTags::normalize($input['tags'])
            : ($report->exists ? $report->tags : []);

        if ($scope === ScheduledReportScope::Tags && $tags === []) {
            throw ValidationException::withMessages([
                'tags' => 'Add at least one tag.',
            ]);
        }

        if ($scope !== ScheduledReportScope::Monitors) {
            return [];
        }

        $incoming = $input['monitor_ids'] ?? $input['monitorIds'] ?? null;
        $ids = is_array($incoming)
            ? $incoming
            : ($report->exists ? $report->monitors()->pluck('monitors.id')->all() : []);

        $ids = Monitor::query()->whereKey($ids)->pluck('id')->all();

        if ($ids === []) {
            throw ValidationException::withMessages([
                'monitor_ids' => 'Select at least one monitor.',
                'monitorIds' => 'Select at least one monitor.',
            ]);
        }

        return $ids;
    }

    private function sendTime(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        $value = trim((string) $value);

        if (! preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            throw ValidationException::withMessages([
                'send_time' => 'Enter a send time.',
            ]);
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw ValidationException::withMessages([
                'send_time' => 'Enter a send time.',
            ]);
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function boundedInt(mixed $value, int $min, int $max, string $field): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $field => 'Enter a number from '.$min.' to '.$max.'.',
            ]);
        }

        $value = (int) $value;

        if ($value < $min || $value > $max) {
            throw ValidationException::withMessages([
                $field => 'Enter a number from '.$min.' to '.$max.'.',
            ]);
        }

        return $value;
    }

    /**
     * @param  class-string<ScheduledReportType|ScheduledReportCadence|ScheduledReportScope>  $enum
     */
    private function enum(string $enum, mixed $value, string $field): ScheduledReportType|ScheduledReportCadence|ScheduledReportScope
    {
        if ($value instanceof $enum) {
            return $value;
        }

        try {
            return EnumValue::parse($enum, $value);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                $field => 'Choose a valid '.$field.'.',
            ]);
        }
    }
}
