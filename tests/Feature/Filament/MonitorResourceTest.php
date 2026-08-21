<?php

declare(strict_types=1);

use App\Enums\AggregateGranularity;
use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use App\Enums\HttpMethod;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\Pages\CreateMonitor;
use App\Filament\Resources\Monitors\Pages\EditMonitor;
use App\Filament\Resources\Monitors\Pages\ListMonitors;
use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Filament\Resources\Monitors\RelationManagers\CheckResultsRelationManager;
use App\Filament\Widgets\MonitorHistoryWidget;
use App\Filament\Widgets\MonitorStatsWidget;
use App\Jobs\RunCheckJob;
use App\Models\CheckAggregate;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\User;
use App\Support\DownMonitorFavicon;
use App\Support\ReverbBrowser;
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

it('links to documentation from the admin user menu', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/monitors')
        ->assertOk()
        ->assertSee('https://staynominal.com/docs')
        ->assertSee('Documentation');
});

it('paginates monitors at 50, 100, or 250 rows', function () {
    $user = User::factory()->create();

    $table = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->instance()
        ->getTable();

    expect($table->getPaginationPageOptions())->toBe([50, 100, 250])
        ->and($table->getDefaultPaginationPageOption())->toBe(50)
        ->and($table->getDefaultGroup())->toBeNull();
});

it('shows tags on monitor cards and filters by tag', function () {
    $user = User::factory()->create();
    $prod = Monitor::factory()->create([
        'name' => 'Checkout API',
        'tags' => ['prod', 'critical'],
    ]);
    $staging = Monitor::factory()->create([
        'name' => 'Checkout staging',
        'tags' => ['staging'],
    ]);

    Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSee('Checkout API')
        ->assertSee('Checkout staging')
        ->assertSee('prod')
        ->assertSee('critical')
        ->assertSee('staging')
        ->filterTable('tag', 'critical')
        ->assertCanSeeTableRecords([$prod])
        ->assertCanNotSeeTableRecords([$staging]);
});

it('saves tags and a description from the create form', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->set('data.name', 'Checkout API')
        ->set('data.tags', [' Payments ', 'prod'])
        ->set('data.description', 'Owned by payments. Page #payments-oncall if this is down.')
        ->set('data.type', MonitorType::Http->value)
        ->set('data.target', 'https://pay.example/health')
        ->set('data.probes', [$probe->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $monitor = Monitor::query()->where('name', 'Checkout API')->first();

    expect($monitor)->not->toBeNull()
        ->and($monitor->tags)->toBe(['Payments', 'prod'])
        ->and($monitor->description)->toBe('Owned by payments. Page #payments-oncall if this is down.');
});

it('shows the description on the monitor view', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create([
        'name' => 'Checkout API',
        'description' => 'Owned by payments. Restart the worker pool if this fails.',
        'tags' => ['prod'],
    ]);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertSee('Monitor Description')
        ->assertSee('Owned by payments. Restart the worker pool if this fails.')
        ->assertSee('prod');
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
        ->assertSee('Healthy')
        ->assertSee('Unhealthy')
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
        ->toContain('data-status="paused"')
        ->toContain('data-status="maintenance"');

    Livewire::actingAs($user)
        ->test(MonitorStatsWidget::class)
        ->assertSee('2')
        ->assertSee('3')
        ->assertSee('4')
        ->assertSee('5');
});

it('shows a red favicon with the down count on the monitors page', function () {
    $user = User::factory()->create();

    Monitor::factory()->count(2)->create(['status' => MonitorStatus::Up]);
    Monitor::factory()->count(3)->create(['status' => MonitorStatus::Down]);
    Monitor::factory()->create(['status' => MonitorStatus::Paused]);

    $href = DownMonitorFavicon::href(3);

    Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSet('faviconHref', $href)
        ->assertSet('downCount', 3)
        ->assertSee('data-nm-favicon="'.$href.'"', escape: false)
        ->assertSee('data-nm-down="3"', escape: false);

    $this->actingAs($user)
        ->get('/admin/monitors')
        ->assertOk()
        ->assertSee('nm-logo-bg', escape: false)
        ->assertSee('html.nm-monitors-down .nm-logo-bg', escape: false);
});

it('keeps the default favicon on the monitors page when none are down', function () {
    $user = User::factory()->create();
    Monitor::factory()->create(['status' => MonitorStatus::Up]);

    Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSet('downCount', 0)
        ->assertSet('faviconHref', asset('favicon.svg'));
});

it('refreshes monitor cards when a reverb event arrives', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create([
        'name' => 'Payments API',
        'status' => MonitorStatus::Up,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSee('Payments API')
        ->assertSet('downCount', 0);

    $monitor->update([
        'name' => 'Billing API',
        'status' => MonitorStatus::Down,
    ]);

    $livewire
        ->dispatch('monitors-updated')
        ->assertSee('Billing API')
        ->assertDontSee('Payments API')
        ->assertSet('downCount', 1);
});

it('refreshes status totals when a reverb event arrives', function () {
    $user = User::factory()->create();
    Monitor::factory()->create(['status' => MonitorStatus::Up]);

    $widget = Livewire::actingAs($user)
        ->test(MonitorStatsWidget::class);

    Monitor::factory()->count(2)->create(['status' => MonitorStatus::Down]);

    $widget
        ->dispatch('monitors-updated')
        ->assertSee('2');
});

it('refreshes the monitor view when a reverb event arrives', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);

    $widget = Livewire::actingAs($user)
        ->test(MonitorHistoryWidget::class, ['record' => $monitor])
        ->assertSee('Healthy');

    $monitor->update(['status' => MonitorStatus::Down]);

    $widget
        ->dispatch('monitors-updated')
        ->assertSee('Unhealthy');
});

it('loads the reverb client on the admin panel', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'key',
        'broadcasting.client.host' => 'localhost',
        'broadcasting.client.port' => 8080,
        'broadcasting.client.scheme' => 'http',
    ]);
    config(['filament.broadcasting.echo' => ReverbBrowser::filamentEcho()]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/monitors')
        ->assertOk()
        ->assertSee('window.Echo = new window.EchoFactory', escape: false)
        ->assertSee('NominalMonitorsEcho', escape: false)
        ->assertSee('.CheckCompleted', escape: false)
        ->assertSee('.MonitorStatusUpdated', escape: false)
        ->assertSee('App.Models.User.'.$user->id, escape: false);
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

it('renders monitors as status cards instead of a table', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->create([
        'name' => 'Payments API',
        'target' => 'https://pay.example/health',
        'status' => MonitorStatus::Up,
    ]);

    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => true,
        'latency_ms' => 42,
        'checked_at' => now()->subMinutes(8),
        'condition_results' => [
            ['expression' => '[STATUS] == 200', 'passed' => true, 'actual' => '200'],
        ],
    ]);

    $table = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->instance()
        ->getTable();

    expect($table->hasColumnsLayout())->toBeTrue()
        ->and($table->getContentGrid())->toBe([
            'md' => 2,
            'xl' => 4,
        ]);

    $html = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSee('Payments API')
        ->assertSee('https://pay.example/health')
        ->assertSee('Healthy')
        ->html();

    expect($html)
        ->toContain('fi-ta-content-grid')
        ->toContain('nm-card')
        ->toContain('nm-card-chart')
        ->toContain('data-heartbeat')
        ->toContain('nm-status-badge')
        ->toContain('TIMESTAMP')
        ->toContain('[STATUS] == 200')
        ->not->toContain('data-uptime-window')
        ->not->toContain('100.00%')
        ->not->toContain('data-latency')
        ->not->toContain('fi-ta-table')
        ->not->toContain('fi-ta-record-checkbox');
});

it('shows a heartbeat of recent checks on the monitors list', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->create(['name' => 'Checkout API']);

    foreach (range(1, 41) as $i) {
        CheckResult::factory()->create([
            'monitor_id' => $monitor->id,
            'probe_id' => $probe->id,
            'success' => $i !== 41,
            'checked_at' => now()->subMinutes(41 - $i),
        ]);
    }

    $html = Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSee('Checkout API')
        ->html();

    expect(substr_count($html, 'data-check="up"'))->toBe(39)
        ->and(substr_count($html, 'data-check="down"'))->toBe(1);
});

it('shows heartbeat and latency on the monitor view', function () {
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

    $html = Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertSeeLivewire(MonitorHistoryWidget::class)
        ->assertSee('Current status')
        ->assertSee('Avg. response')
        ->assertSee('Response range')
        ->assertSee('42ms–90ms')
        ->assertSee('Recent checks')
        ->assertSee('Response time')
        ->assertSee('Uptime')
        ->assertDontSee('Last 7 days by hour')
        ->assertDontSee('data-heatmap')
        ->html();

    expect($html)
        ->toContain('data-trend')
        ->toContain('nm-trend-hit')
        ->toContain('preserveAspectRatio="none"')
        ->toContain('TIMESTAMP')
        ->toContain('RESPONSE TIME')
        ->toContain('data-uptime-window="1h"')
        ->toContain('data-uptime-window="24h"')
        ->toContain('data-uptime-window="7d"')
        ->toContain('data-uptime-window="30d"');

    expect(strpos($html, 'nm-section-title">Response time'))
        ->toBeLessThan(strpos($html, 'nm-section-title">Uptime'));
});

it('shows latency in seconds when a check takes a second or more', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->create();

    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => true,
        'latency_ms' => 1500,
        'checked_at' => now()->subMinutes(2),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => true,
        'latency_ms' => 2500,
        'checked_at' => now()->subMinute(),
    ]);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertSee('1.5s–2.5s')
        ->assertSee('2s')
        ->assertDontSee('1500ms')
        ->assertDontSee('2500ms');
});

it('plots 24h hourly latency from rollups on the monitor view', function () {
    $this->freezeTime();

    $user = User::factory()->create();
    $monitor = Monitor::factory()->create();

    CheckAggregate::query()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => null,
        'period_start' => now()->subHours(2)->startOfHour(),
        'granularity' => AggregateGranularity::Hour,
        'up_count' => 10,
        'down_count' => 0,
        'avg_latency_ms' => 120,
    ]);
    CheckAggregate::query()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => null,
        'period_start' => now()->subHour()->startOfHour(),
        'granularity' => AggregateGranularity::Hour,
        'up_count' => 9,
        'down_count' => 1,
        'avg_latency_ms' => 80,
    ]);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertSee('80ms–120ms')
        ->assertSee('100ms');
});

it('shows the last 10 checks in the monitor history table', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->create();

    $results = collect(range(1, 12))->map(fn (int $i) => CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'checked_at' => now()->subMinutes(12 - $i),
        'latency_ms' => $i * 10,
    ]));

    $livewire = Livewire::actingAs($user)
        ->test(CheckResultsRelationManager::class, [
            'ownerRecord' => $monitor,
            'pageClass' => ViewMonitor::class,
        ]);

    expect($livewire->instance()->getTable()->getPaginationPageOptions())->toBe([10])
        ->and($livewire->instance()->getTable()->getDefaultPaginationPageOption())->toBe(10);

    $livewire
        ->assertCanSeeTableRecords($results->slice(-10)->values()->all())
        ->assertCanNotSeeTableRecords([$results->first()]);
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

it('hides check now and shows the heartbeat url on the monitor view', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->heartbeat()->create();

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertActionHidden('checkNow')
        ->assertSee($monitor->heartbeatUrl());
});

it('shows embeddable badge urls on the monitor view', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertSee($monitor->statusBadgeSvgUrl())
        ->assertSee($monitor->uptimeBadgeSvgUrl())
        ->assertSee($monitor->latencyBadgeSvgUrl())
        ->assertSee($monitor->badgeMarkdown());
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

it('saves a domain expiration condition', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->set('data.name', 'example.com')
        ->set('data.type', MonitorType::Http->value)
        ->set('data.target', 'https://example.com')
        ->set('data.interval_seconds', 3600)
        ->set('data.probes', [$probe->id])
        ->set('data.conditions', [
            'domain' => [
                'placeholder' => ConditionPlaceholder::DomainExpiration->value,
                'comparator' => ConditionComparator::GreaterThan->value,
                'value' => '720h',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $monitor = Monitor::query()->where('name', 'example.com')->first();

    expect($monitor)->not->toBeNull()
        ->and($monitor->interval_seconds)->toBe(3600)
        ->and($monitor->conditions()->pluck('expression')->all())->toBe([
            '[DOMAIN_EXPIRATION] > 720h',
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

it('defaults graphql monitors to POST and a 200-299 status range', function () {
    $user = User::factory()->create();

    $livewire = Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->set('data.type', MonitorType::GraphQL->value);

    $conditions = array_values($livewire->get('data.conditions'));

    expect($livewire->get('data.method'))->toBe(HttpMethod::Post->value)
        ->and($conditions)->toHaveCount(2)
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

it('creates a heartbeat monitor without probes, IP family, or conditions', function () {
    $user = User::factory()->create();
    Probe::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->set('data.name', 'Nightly backup')
        ->set('data.type', MonitorType::Heartbeat->value)
        ->set('data.target', 'backup-job')
        ->call('create')
        ->assertHasNoFormErrors();

    $monitor = Monitor::query()->where('name', 'Nightly backup')->first();

    expect($monitor)->not->toBeNull()
        ->and($monitor->type)->toBe(MonitorType::Heartbeat)
        ->and($monitor->probes()->count())->toBe(0)
        ->and($monitor->conditions()->count())->toBe(0)
        ->and($monitor->heartbeat_token)->toHaveLength(48);
});

it('shows start, finish, and error heartbeat urls on the edit form', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->heartbeat()->create();

    Livewire::actingAs($user)
        ->test(EditMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertFormSet([
            'heartbeat_url' => $monitor->heartbeatUrl(),
            'heartbeat_start_url' => $monitor->heartbeatStartUrl(),
            'heartbeat_finish_url' => $monitor->heartbeatFinishUrl(),
            'heartbeat_error_url' => $monitor->heartbeatErrorUrl(),
        ]);
});

it('hides probe timeout on heartbeat monitors', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->set('data.type', MonitorType::Heartbeat->value)
        ->assertFormFieldIsHidden('timeout_seconds')
        ->assertFormFieldIsHidden('ip_family')
        ->assertFormFieldIsHidden('probes')
        ->assertFormFieldIsHidden('proxy_url');
});

it('shows a proxy url field for HTTP, GraphQL, TCP, TLS, WebSocket, and Redis monitors', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->assertFormFieldIsVisible('proxy_url')
        ->set('data.type', MonitorType::GraphQL->value)
        ->assertFormFieldIsVisible('proxy_url')
        ->set('data.type', MonitorType::Tcp->value)
        ->assertFormFieldIsVisible('proxy_url')
        ->set('data.type', MonitorType::Redis->value)
        ->assertFormFieldIsVisible('proxy_url')
        ->set('data.type', MonitorType::Mysql->value)
        ->assertFormFieldIsHidden('proxy_url')
        ->set('data.type', MonitorType::Postgres->value)
        ->assertFormFieldIsHidden('proxy_url')
        ->set('data.type', MonitorType::Ping->value)
        ->assertFormFieldIsHidden('proxy_url');
});
