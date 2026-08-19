<?php

declare(strict_types=1);

use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\Pages\CreateMonitor;
use App\Filament\Resources\Monitors\Pages\EditMonitor;
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

it('saves monitor conditions from the placeholder picker', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->set('data.name', 'Health API')
        ->set('data.type', MonitorType::Http->value)
        ->set('data.target', 'https://example.com/health')
        ->set('data.probes', [$probe->id])
        ->set('data.conditions', [
            'status' => [
                'placeholder' => ConditionPlaceholder::Status->value,
                'comparator' => ConditionComparator::Equal->value,
                'value' => '200',
            ],
            'body' => [
                'placeholder' => ConditionPlaceholder::Body->value,
                'path' => '.status',
                'comparator' => ConditionComparator::Equal->value,
                'value' => 'UP',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $monitor = Monitor::query()->where('name', 'Health API')->first();

    expect($monitor)->not->toBeNull()
        ->and($monitor->conditions()->orderBy('sort')->pluck('expression')->all())->toBe([
            '[STATUS] == 200',
            '[BODY].status == UP',
        ]);
});

it('hydrates the condition picker from stored expressions', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['name' => 'Checkout']);
    $monitor->conditions()->create([
        'expression' => '[BODY].user.name == john.doe',
        'sort' => 0,
    ]);

    Livewire::actingAs($user)
        ->test(EditMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertFormSet(function (array $state): array {
            $item = array_values($state['conditions'] ?? [])[0] ?? [];

            expect($item)
                ->toHaveKey('placeholder', ConditionPlaceholder::Body->value)
                ->toHaveKey('path', '.user.name')
                ->toHaveKey('comparator', ConditionComparator::Equal->value)
                ->toHaveKey('value', 'john.doe');

            return [];
        });
});

it('defaults new http monitors to a 200-299 status range', function () {
    $user = User::factory()->create();

    $conditions = array_values(Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->get('data.conditions'));

    expect($conditions)->toHaveCount(2)
        ->and($conditions[0])->toMatchArray([
            'placeholder' => ConditionPlaceholder::Status->value,
            'comparator' => ConditionComparator::GreaterThanOrEqual->value,
            'value' => '200',
        ])
        ->and($conditions[1])->toMatchArray([
            'placeholder' => ConditionPlaceholder::Status->value,
            'comparator' => ConditionComparator::LessThanOrEqual->value,
            'value' => '299',
        ]);
});

it('defaults ping monitors to connected and under 50ms', function () {
    $user = User::factory()->create();

    $conditions = array_values(Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->set('data.type', MonitorType::Ping->value)
        ->get('data.conditions'));

    expect($conditions)->toHaveCount(2)
        ->and($conditions[0])->toMatchArray([
            'placeholder' => ConditionPlaceholder::Connected->value,
            'comparator' => ConditionComparator::Equal->value,
            'value' => 'true',
        ])
        ->and($conditions[1])->toMatchArray([
            'placeholder' => ConditionPlaceholder::ResponseTime->value,
            'comparator' => ConditionComparator::LessThan->value,
            'value' => '50',
        ]);
});
