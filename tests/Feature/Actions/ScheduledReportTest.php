<?php

declare(strict_types=1);

use App\Actions\ComputeUptimeReport;
use App\Actions\NextScheduledReportRun;
use App\Actions\ResolveScheduledReportPeriod;
use App\Actions\SaveScheduledReport;
use App\Actions\SendScheduledReport;
use App\Enums\AggregateGranularity;
use App\Enums\ScheduledReportCadence;
use App\Enums\ScheduledReportScope;
use App\Enums\ScheduledReportType;
use App\Mail\ScheduledReportMail;
use App\Models\CheckAggregate;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\ScheduledReport;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;

it('schedules a new weekly report for the following monday morning', function () {
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00'));

    $report = SaveScheduledReport::make()->handle([
        'name' => 'Ops weekly',
        'recipients' => ['A@Example.com', ' a@example.com '],
    ]);

    expect($report->type)->toBe(ScheduledReportType::Uptime)
        ->and($report->cadence)->toBe(ScheduledReportCadence::Weekly)
        ->and($report->weekday)->toBe(1)
        ->and($report->send_time)->toBe('08:00')
        ->and($report->scope)->toBe(ScheduledReportScope::All)
        ->and($report->recipients)->toBe(['a@example.com'])
        ->and($report->next_run_at?->toDateTimeString())->toBe('2026-10-12 08:00:00');
});

it('keeps the next run strictly in the future', function () {
    $report = ScheduledReport::factory()->create([
        'cadence' => ScheduledReportCadence::Weekly,
        'weekday' => 1,
        'send_time' => '08:00',
    ]);

    $this->travelTo(Carbon::parse('2026-10-05 07:30:00'));
    expect(NextScheduledReportRun::make()->handle($report)->toDateTimeString())->toBe('2026-10-05 08:00:00');

    $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
    expect(NextScheduledReportRun::make()->handle($report)->toDateTimeString())->toBe('2026-10-12 08:00:00');

    $report->cadence = ScheduledReportCadence::Daily;
    $this->travelTo(Carbon::parse('2026-10-07 08:00:00'));
    expect(NextScheduledReportRun::make()->handle($report)->toDateTimeString())->toBe('2026-10-08 08:00:00');

    $report->cadence = ScheduledReportCadence::Monthly;
    $report->day_of_month = 1;
    $this->travelTo(Carbon::parse('2026-10-01 07:00:00'));
    expect(NextScheduledReportRun::make()->handle($report)->toDateTimeString())->toBe('2026-10-01 08:00:00');

    $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
    expect(NextScheduledReportRun::make()->handle($report)->toDateTimeString())->toBe('2026-11-01 08:00:00');

    $report->day_of_month = 28;
    $this->travelTo(Carbon::parse('2026-02-28 09:00:00'));
    expect(NextScheduledReportRun::make()->handle($report)->toDateTimeString())->toBe('2026-03-28 08:00:00');
});

it('covers the previous completed calendar period', function () {
    $report = ScheduledReport::factory()->create();

    $this->travelTo(Carbon::parse('2026-10-07 08:00:00'));
    $report->cadence = ScheduledReportCadence::Daily;
    $daily = ResolveScheduledReportPeriod::make()->handle($report);
    expect($daily->startsAt->toDateTimeString())->toBe('2026-10-06 00:00:00')
        ->and($daily->endsAt->toDateTimeString())->toBe('2026-10-07 00:00:00');

    $report->cadence = ScheduledReportCadence::Weekly;
    $report->weekday = 1;
    $weekly = ResolveScheduledReportPeriod::make()->handle($report);
    expect($weekly->startsAt->toDateTimeString())->toBe('2026-09-28 00:00:00')
        ->and($weekly->endsAt->toDateTimeString())->toBe('2026-10-05 00:00:00');

    $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
    $onMonday = ResolveScheduledReportPeriod::make()->handle($report);
    expect($onMonday->startsAt->toDateTimeString())->toBe('2026-09-28 00:00:00')
        ->and($onMonday->endsAt->toDateTimeString())->toBe('2026-10-05 00:00:00');

    $report->cadence = ScheduledReportCadence::Monthly;
    $this->travelTo(Carbon::parse('2026-03-01 08:00:00'));
    $monthly = ResolveScheduledReportPeriod::make()->handle($report);
    expect($monthly->startsAt->toDateTimeString())->toBe('2026-02-01 00:00:00')
        ->and($monthly->endsAt->toDateTimeString())->toBe('2026-03-01 00:00:00');
});

it('resolves all monitors, a selected list, or any matching tag', function () {
    $included = Monitor::factory()->create(['name' => 'API', 'tags' => ['prod'], 'enabled' => false]);
    $tagged = Monitor::factory()->create(['name' => 'Web', 'tags' => ['edge']]);
    $other = Monitor::factory()->create(['name' => 'Quiet', 'tags' => ['internal']]);
    $deleted = Monitor::factory()->create(['name' => 'Gone']);
    $deleted->delete();

    $all = ScheduledReport::factory()->create(['scope' => ScheduledReportScope::All]);
    $names = ComputeUptimeReport::make()->handle($all)->rows;
    expect(collect($names)->pluck('name')->all())->toBe(['API', 'Quiet', 'Web']);

    $picked = ScheduledReport::factory()->withMonitors([$included])->create([
        'scope' => ScheduledReportScope::Monitors,
    ]);
    expect(collect(ComputeUptimeReport::make()->handle($picked)->rows)->pluck('name')->all())->toBe(['API']);

    $byTag = ScheduledReport::factory()->create([
        'scope' => ScheduledReportScope::Tags,
        'tags' => ['edge'],
    ]);
    expect(collect(ComputeUptimeReport::make()->handle($byTag)->rows)->pluck('name')->all())->toBe(['Web'])
        ->and($other->name)->toBe('Quiet');
});

it('requires recipients, monitors, or tags for the chosen scope', function () {
    expect(fn () => SaveScheduledReport::make()->handle([
        'name' => 'Ops',
        'recipients' => ['not-an-email'],
    ]))->toThrow(ValidationException::class);

    expect(fn () => SaveScheduledReport::make()->handle([
        'name' => 'Ops',
        'recipients' => [],
    ]))->toThrow(ValidationException::class);

    expect(fn () => SaveScheduledReport::make()->handle([
        'name' => 'Ops',
        'recipients' => ['ops@example.com'],
        'scope' => ScheduledReportScope::Monitors,
        'monitor_ids' => [],
    ]))->toThrow(ValidationException::class);

    expect(fn () => SaveScheduledReport::make()->handle([
        'name' => 'Ops',
        'recipients' => ['ops@example.com'],
        'scope' => 'tags',
        'tags' => [],
    ]))->toThrow(ValidationException::class);

    expect(fn () => SaveScheduledReport::make()->handle([
        'name' => 'Ops',
        'recipients' => ['ops@example.com'],
        'type' => 'latency',
    ]))->toThrow(ValidationException::class);
});

it('weights uptime across monitors and counts down transitions', function () {
    $this->travelTo(Carbon::parse('2026-10-07 08:00:00'));

    $healthy = Monitor::factory()->create(['name' => 'Healthy']);
    $flaky = Monitor::factory()->create(['name' => 'Flaky']);
    $quiet = Monitor::factory()->create(['name' => 'Quiet']);
    $probe = Probe::factory()->create();

    CheckAggregate::query()->create([
        'monitor_id' => $healthy->id,
        'probe_id' => null,
        'period_start' => Carbon::parse('2026-10-06 00:00:00'),
        'granularity' => AggregateGranularity::Day,
        'up_count' => 10,
        'down_count' => 0,
    ]);
    CheckAggregate::query()->create([
        'monitor_id' => $healthy->id,
        'probe_id' => $probe->id,
        'period_start' => Carbon::parse('2026-10-06 00:00:00'),
        'granularity' => AggregateGranularity::Day,
        'up_count' => 0,
        'down_count' => 500,
    ]);
    CheckAggregate::query()->create([
        'monitor_id' => $flaky->id,
        'probe_id' => null,
        'period_start' => Carbon::parse('2026-10-06 00:00:00'),
        'granularity' => AggregateGranularity::Day,
        'up_count' => 0,
        'down_count' => 90,
    ]);

    CheckResult::factory()->create([
        'monitor_id' => $flaky->id,
        'probe_id' => $probe->id,
        'success' => true,
        'checked_at' => Carbon::parse('2026-10-05 23:00:00'),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $flaky->id,
        'probe_id' => $probe->id,
        'success' => false,
        'checked_at' => Carbon::parse('2026-10-06 01:00:00'),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $flaky->id,
        'probe_id' => $probe->id,
        'success' => false,
        'checked_at' => Carbon::parse('2026-10-06 02:00:00'),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $flaky->id,
        'probe_id' => $probe->id,
        'success' => true,
        'checked_at' => Carbon::parse('2026-10-06 03:00:00'),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $flaky->id,
        'probe_id' => $probe->id,
        'success' => false,
        'checked_at' => Carbon::parse('2026-10-06 04:00:00'),
    ]);

    $report = ScheduledReport::factory()->create(['cadence' => ScheduledReportCadence::Daily]);
    $summary = ComputeUptimeReport::make()->handle($report);

    expect($summary->uptimePercent)->toBe(10.0)
        ->and($summary->downtimeSeconds)->toBe(77760)
        ->and($summary->downtimeLabel())->toBe('21h 36m')
        ->and($summary->outages)->toBe(2)
        ->and($summary->rows[0]->name)->toBe('Flaky')
        ->and($summary->rows[0]->uptimeLabel())->toBe('0.00%')
        ->and($summary->rows[0]->downtimeLabel())->toBe('24h')
        ->and($summary->rows[0]->outages)->toBe(2)
        ->and($summary->rows[1]->name)->toBe('Healthy')
        ->and($summary->rows[1]->uptimePercent)->toBe(100.0)
        ->and($summary->rows[1]->downtimeLabel())->toBe('0m')
        ->and($summary->rows[2]->name)->toBe('Quiet')
        ->and($summary->rows[2]->uptimeLabel())->toBe('no data')
        ->and($quiet->exists)->toBeTrue();
});

it('does not count an outage that is already in progress', function () {
    $this->travelTo(Carbon::parse('2026-10-07 08:00:00'));
    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create();

    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => false,
        'checked_at' => Carbon::parse('2026-10-05 22:00:00'),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => false,
        'checked_at' => Carbon::parse('2026-10-06 01:00:00'),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => true,
        'checked_at' => Carbon::parse('2026-10-06 02:00:00'),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => false,
        'checked_at' => Carbon::parse('2026-10-06 03:00:00'),
    ]);

    $report = ScheduledReport::factory()->create(['cadence' => ScheduledReportCadence::Daily]);
    $summary = ComputeUptimeReport::make()->handle($report);

    expect($summary->outages)->toBe(1)
        ->and($summary->rows[0]->uptimePercent)->toBe(33.3333);
});

it('fills a day from check results when the daily aggregate is missing', function () {
    $this->travelTo(Carbon::parse('2026-10-07 08:00:00'));
    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create();

    foreach ([true, true, true, false] as $index => $success) {
        CheckResult::factory()->create([
            'monitor_id' => $monitor->id,
            'probe_id' => $probe->id,
            'success' => $success,
            'checked_at' => Carbon::parse('2026-10-06 0'.($index + 1).':00:00'),
        ]);
    }

    $report = ScheduledReport::factory()->create(['cadence' => ScheduledReportCadence::Daily]);
    $summary = ComputeUptimeReport::make()->handle($report);

    expect($summary->uptimePercent)->toBe(75.0)
        ->and($summary->downtimeSeconds)->toBe(21600)
        ->and($summary->downtimeLabel())->toBe('6h');
});

it('notes when retention starts after the period', function () {
    $this->travelTo(Carbon::parse('2026-10-07 08:00:00'));
    Monitor::factory()->create(['retention_days' => 5]);
    Monitor::factory()->create(['retention_days' => 10]);

    $report = ScheduledReport::factory()->create(['cadence' => ScheduledReportCadence::Monthly]);
    $summary = ComputeUptimeReport::make()->handle($report);

    expect($summary->note)->toBe('Data starts Oct 2, 2026; earlier checks were pruned.');
});

it('emails every recipient and still advances the schedule when sending fails', function () {
    $this->travelTo(Carbon::parse('2026-10-07 08:00:00'));
    Mail::fake();

    $report = ScheduledReport::factory()->create([
        'name' => 'Weekly uptime',
        'cadence' => ScheduledReportCadence::Daily,
        'recipients' => ['ops@example.com', 'lead@example.com'],
        'next_run_at' => now()->addWeek(),
    ]);
    $next = $report->next_run_at?->toDateTimeString();

    $sent = SendScheduledReport::make()->handle($report, advanceSchedule: false);

    expect($sent->last_sent_at)->not->toBeNull()
        ->and($sent->last_error)->toBeNull()
        ->and($sent->next_run_at?->toDateTimeString())->toBe($next);

    Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail): bool {
        return $mail->hasTo('ops@example.com')
            && $mail->hasTo('lead@example.com')
            && $mail->envelope()->subject === 'Weekly uptime: Oct 6, 2026–Oct 7, 2026';
    });
});

it('records a mail failure and moves the next run forward', function () {
    $report = ScheduledReport::factory()->create([
        'next_run_at' => now()->subMinute(),
    ]);

    $pending = Mockery::mock(PendingMail::class);
    $pending->shouldReceive('send')->andThrow(new RuntimeException('smtp down'));
    Mail::shouldReceive('to')->once()->andReturn($pending);

    $sent = SendScheduledReport::make()->handle($report);

    expect($sent->last_error)->toBe('smtp down')
        ->and($sent->last_sent_at)->toBeNull()
        ->and($sent->next_run_at?->greaterThan(now()->subMinute()))->toBeTrue();
});

it('sends due reports and skips disabled or future ones', function () {
    Mail::fake();

    $due = ScheduledReport::factory()->create([
        'name' => 'Due',
        'next_run_at' => now()->subMinute(),
    ]);
    $later = ScheduledReport::factory()->create([
        'name' => 'Later',
        'next_run_at' => now()->addDay(),
    ]);
    $disabled = ScheduledReport::factory()->create([
        'name' => 'Off',
        'enabled' => false,
        'next_run_at' => now()->subMinute(),
    ]);

    $this->artisan('nominal:send-due-scheduled-reports')->assertSuccessful();

    Mail::assertSent(ScheduledReportMail::class, 1);
    expect($due->fresh()?->last_sent_at)->not->toBeNull()
        ->and($later->fresh()?->last_sent_at)->toBeNull()
        ->and($disabled->fresh()?->last_sent_at)->toBeNull();
});

it('registers the due report command every minute', function () {
    $event = collect(Schedule::events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'nominal:send-due-scheduled-reports'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});
