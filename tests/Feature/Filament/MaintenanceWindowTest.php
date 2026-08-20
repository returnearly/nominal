<?php

declare(strict_types=1);

use App\Enums\MonitorStatus;
use App\Filament\Resources\MaintenanceWindows\Pages\CreateMaintenanceWindow;
use App\Filament\Resources\Monitors\Pages\ListMonitors;
use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Filament\Widgets\MonitorStatsWidget;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\User;
use Livewire\Livewire;

it('lists maintenance windows in the admin panel', function () {
    $user = User::factory()->create();
    MaintenanceWindow::factory()->forAll()->create(['title' => 'Database upgrade']);

    $this->actingAs($user)
        ->get('/admin/maintenance')
        ->assertOk()
        ->assertSee('Database upgrade');
});

it('creates a global maintenance window from the admin panel', function () {
    $user = User::factory()->create();
    Monitor::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateMaintenanceWindow::class)
        ->set('data.title', 'Site-wide maintenance')
        ->set('data.applies_to_all', true)
        ->set('data.starts_at', now())
        ->call('create')
        ->assertHasNoFormErrors();

    $window = MaintenanceWindow::query()->where('title', 'Site-wide maintenance')->first();

    expect($window)->not->toBeNull()
        ->and($window->applies_to_all)->toBeTrue()
        ->and($window->phase())->toBe('active');
});

it('shows maintenance on monitor cards and in status totals', function () {
    $user = User::factory()->create();
    $up = Monitor::factory()->create(['name' => 'Healthy API', 'status' => MonitorStatus::Up]);
    $maintained = Monitor::factory()->create(['name' => 'Payments API', 'status' => MonitorStatus::Down]);
    MaintenanceWindow::factory()->withMonitors([$maintained])->create([
        'title' => 'Database upgrade',
        'message' => 'Upgrading Postgres.',
    ]);

    $html = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSee('Payments API')
        ->assertSee('Upgrading Postgres.')
        ->assertSee('Maintenance')
        ->html();

    expect($html)
        ->toContain('data-status="maintenance"')
        ->toContain('nm-card-maintenance');

    Livewire::actingAs($user)
        ->test(MonitorStatsWidget::class)
        ->assertSee('1');

    Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->dispatch('filter-monitors-by-status', status: MonitorStatus::Maintenance->value)
        ->assertCanSeeTableRecords([$maintained])
        ->assertCanNotSeeTableRecords([$up]);
});

it('starts and ends maintenance from the monitor view', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Down]);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertActionExists('startMaintenance')
        ->callAction('startMaintenance', [
            'title' => 'Patching',
            'message' => 'Restarting the API.',
        ])
        ->assertNotified('Maintenance started')
        ->assertActionHidden('startMaintenance')
        ->assertActionExists('endMaintenance')
        ->callAction('endMaintenance')
        ->assertNotified('Maintenance ended');

    expect($monitor->fresh()->isUnderMaintenance())->toBeFalse();
});
