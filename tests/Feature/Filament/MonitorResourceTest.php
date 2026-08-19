<?php

declare(strict_types=1);

use App\Enums\MonitorStatus;
use App\Filament\Resources\Monitors\Pages\ListMonitors;
use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Filament\Widgets\MonitorHistoryWidget;
use App\Filament\Widgets\MonitorStatsWidget;
use App\Jobs\RunCheckJob;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it('shows monitors in the admin panel', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->create(['name' => 'Payments API']);
    $monitor->probes()->attach($probe);

    $this->actingAs($user)
        ->get('/admin/monitors')
        ->assertOk()
        ->assertSee('Payments API');
});

it('does not register a dashboard page', function () {
    expect(Route::has('filament.admin.pages.dashboard'))->toBeFalse();
});

it('puts admin navigation in the top bar', function () {
    expect(filament()->getDefaultPanel()->hasTopNavigation())->toBeTrue();
});

it('paginates monitors at 50, 100, or 250 rows', function () {
    $user = User::factory()->create();

    $table = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->instance()
        ->getTable();

    expect($table->getPaginationPageOptions())->toBe([50, 100, 250])
        ->and($table->getDefaultPaginationPageOption())->toBe(50);
});

it('groups the monitors table by group', function () {
    $user = User::factory()->create();
    Monitor::factory()->create(['name' => 'Checkout', 'group' => 'payments']);
    Monitor::factory()->create(['name' => 'Status page', 'group' => 'public']);

    $livewire = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSee('Checkout')
        ->assertSee('Status page')
        ->assertSee('payments')
        ->assertSee('public');

    expect($livewire->instance()->getTable()->getDefaultGroup()?->getColumn())->toBe('group');
});

it('shows status totals at the top of the monitors list', function () {
    $user = User::factory()->create();

    Monitor::factory()->count(2)->create(['status' => MonitorStatus::Up]);
    Monitor::factory()->count(3)->create(['status' => MonitorStatus::Down]);
    Monitor::factory()->count(4)->create(['status' => MonitorStatus::Pending]);
    Monitor::factory()->count(5)->create(['status' => MonitorStatus::Paused]);

    Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSeeLivewire(MonitorStatsWidget::class)
        ->assertSee('Up')
        ->assertSee('Down')
        ->assertSee('Pending')
        ->assertSee('Paused');

    $listHtml = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->html();

    expect($listHtml)
        ->toContain('fi-wi-stats-overview')
        ->toContain('data-status="up"')
        ->toContain('data-status="down"')
        ->toContain('data-status="pending"')
        ->toContain('data-status="paused"');

    Livewire::actingAs($user)
        ->test(MonitorStatsWidget::class)
        ->assertSee('2')
        ->assertSee('3')
        ->assertSee('4')
        ->assertSee('5');
});

it('filters the monitors table when a status stat is clicked', function () {
    $user = User::factory()->create();
    $up = Monitor::factory()->create(['name' => 'Healthy API', 'status' => MonitorStatus::Up]);
    $down = Monitor::factory()->create(['name' => 'Broken API', 'status' => MonitorStatus::Down]);

    Livewire::actingAs($user)
        ->test(MonitorStatsWidget::class)
        ->call('filterByStatus', MonitorStatus::Down->value)
        ->assertDispatched('filter-monitors-by-status', status: MonitorStatus::Down->value);

    Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertCanSeeTableRecords([$up, $down])
        ->dispatch('filter-monitors-by-status', status: MonitorStatus::Down->value)
        ->assertCanSeeTableRecords([$down])
        ->assertCanNotSeeTableRecords([$up])
        ->dispatch('filter-monitors-by-status', status: MonitorStatus::Down->value)
        ->assertCanSeeTableRecords([$up, $down]);
});

it('shows a heartbeat of the latest 20 checks on the monitors list', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->create(['name' => 'Checkout API']);

    foreach (range(1, 21) as $i) {
        CheckResult::factory()->create([
            'monitor_id' => $monitor->id,
            'probe_id' => $probe->id,
            'success' => $i !== 21,
            'checked_at' => now()->subMinutes(21 - $i),
        ]);
    }

    $html = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSee('Checkout API')
        ->html();

    expect(substr_count($html, 'data-check="up"'))->toBe(19)
        ->and(substr_count($html, 'data-check="down"'))->toBe(1);
});

it('shows heartbeat, latency, and heatmap on the monitor view', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->create();

    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => true,
        'latency_ms' => 42,
        'checked_at' => now()->subMinutes(2),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => false,
        'latency_ms' => 90,
        'checked_at' => now()->subMinute(),
    ]);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertSeeLivewire(MonitorHistoryWidget::class)
        ->assertSee('data-latency', false)
        ->assertSee('data-heatmap', false);
});

it('queues a check immediately from the monitor view page', function () {
    Queue::fake();

    $user = User::factory()->create();
    $probe = Probe::factory()->create(['queue' => 'checks.local']);
    $monitor = Monitor::factory()->create();
    $monitor->probes()->attach($probe);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertActionExists('checkNow')
        ->callAction('checkNow');

    Queue::assertPushedOn('checks.local', function (RunCheckJob $job) use ($monitor, $probe): bool {
        return $job->monitorId === $monitor->id && $job->probeId === $probe->id;
    });
});
