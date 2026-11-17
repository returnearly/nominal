<?php

declare(strict_types=1);

use App\Enums\ScheduledReportCadence;
use App\Filament\Resources\ScheduledReports\Pages\CreateScheduledReport;
use App\Filament\Resources\ScheduledReports\Pages\ListScheduledReports;
use App\Mail\ScheduledReportMail;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('lists scheduled reports in the admin panel', function () {
    $user = User::factory()->create();
    ScheduledReport::factory()->create(['name' => 'Monday uptime']);

    $this->actingAs($user)
        ->get('/admin/scheduled-reports')
        ->assertOk();

    Livewire::actingAs($user)
        ->test(ListScheduledReports::class)
        ->loadTable()
        ->assertSee('Monday uptime')
        ->assertSee('Weekly on Monday at 08:00');
});

it('defaults a new report to weekly monday at 08:00', function () {
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00'));
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateScheduledReport::class)
        ->assertSet('data.cadence', ScheduledReportCadence::Weekly->value)
        ->assertSet('data.weekday', 1)
        ->set('data.name', 'Ops weekly')
        ->set('data.recipients', ['ops@example.com', 'lead@example.com'])
        ->call('create')
        ->assertHasNoFormErrors();

    $report = ScheduledReport::query()->where('name', 'Ops weekly')->first();

    expect($report)->not->toBeNull()
        ->and($report->cadence)->toBe(ScheduledReportCadence::Weekly)
        ->and($report->weekday)->toBe(1)
        ->and($report->send_time)->toBe('08:00')
        ->and($report->recipients)->toBe(['ops@example.com', 'lead@example.com'])
        ->and($report->next_run_at?->toDateTimeString())->toBe('2026-10-12 08:00:00');
});

it('sends a report now without moving the next run', function () {
    Mail::fake();
    $user = User::factory()->create();
    $report = ScheduledReport::factory()->create([
        'next_run_at' => Carbon::parse('2026-10-12 08:00:00'),
    ]);

    Livewire::actingAs($user)
        ->test(ListScheduledReports::class)
        ->callTableAction('sendNow', $report)
        ->assertNotified('Report sent');

    expect($report->fresh()?->next_run_at?->toDateTimeString())->toBe('2026-10-12 08:00:00')
        ->and($report->fresh()?->last_sent_at)->not->toBeNull();

    Mail::assertSent(ScheduledReportMail::class);
});
