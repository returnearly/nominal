<?php

declare(strict_types=1);

use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\Pages\CreateMonitor;
use App\Filament\Resources\Probes\Pages\CreateProbe;
use App\Filament\Resources\Probes\Pages\EditProbe;
use App\Filament\Resources\Probes\Pages\ListProbes;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('lists probes in the admin panel', function () {
    $user = User::factory()->create();
    Probe::factory()->create(['name' => 'US East']);

    $this->actingAs($user)
        ->get('/admin/probes')
        ->assertOk()
        ->assertSee('US East');
});

it('creates a default probe and applies it to existing monitors', function () {
    $user = User::factory()->create();
    $http = Monitor::factory()->create();
    $heartbeat = Monitor::factory()->heartbeat()->create();

    Livewire::actingAs($user)
        ->test(CreateProbe::class)
        ->set('data.name', 'EU West')
        ->set('data.slug', 'eu-west')
        ->set('data.queue', 'checks.eu-west')
        ->set('data.enabled', true)
        ->set('data.is_default', true)
        ->set('data.apply_to_existing_monitors', true)
        ->call('create')
        ->assertHasNoFormErrors();

    $probe = Probe::query()->where('slug', 'eu-west')->first();

    expect($probe)->not->toBeNull()
        ->and($probe?->is_default)->toBeTrue()
        ->and($http->fresh()->probes()->where('probes.id', $probe?->id)->exists())->toBeTrue()
        ->and($heartbeat->fresh()->probes()->exists())->toBeFalse();
});

it('does not apply a new default probe to existing monitors unless asked', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateProbe::class)
        ->set('data.name', 'EU West')
        ->set('data.slug', 'eu-west')
        ->set('data.queue', 'checks.eu-west')
        ->set('data.is_default', true)
        ->set('data.apply_to_existing_monitors', false)
        ->call('create')
        ->assertHasNoFormErrors();

    expect($monitor->fresh()->probes()->count())->toBe(0);
});

it('hides the apply to existing checkbox until default is selected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateProbe::class)
        ->assertFormFieldIsHidden('apply_to_existing_monitors')
        ->set('data.is_default', true)
        ->assertFormFieldIsVisible('apply_to_existing_monitors');
});

it('applies an existing probe to monitors when default is saved with apply checked', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create(['is_default' => false]);

    Livewire::actingAs($user)
        ->test(EditProbe::class, ['record' => $probe->getRouteKey()])
        ->set('data.is_default', true)
        ->set('data.apply_to_existing_monitors', true)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($probe->fresh()?->is_default)->toBeTrue()
        ->and($monitor->fresh()->probes()->where('probes.id', $probe->id)->exists())->toBeTrue();
});

it('preselects default probes when creating a monitor', function () {
    $user = User::factory()->create();
    $default = Probe::factory()->asDefault()->create(['name' => 'US East']);
    Probe::factory()->create(['name' => 'EU West']);

    Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->assertFormSet([
            'probes' => [$default->id],
        ]);
});

it('attaches default probes when creating a monitor without choosing probes', function () {
    Queue::fake();

    $user = User::factory()->create();
    $default = Probe::factory()->asDefault()->create();
    Probe::factory()->create(['is_default' => false]);

    Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->set('data.name', 'Health API')
        ->set('data.type', MonitorType::Http->value)
        ->set('data.target', 'https://example.com/health')
        ->call('create')
        ->assertHasNoFormErrors();

    $monitor = Monitor::query()->where('name', 'Health API')->first();

    expect($monitor)->not->toBeNull()
        ->and($monitor?->probes()->pluck('id')->all())->toBe([$default->id]);
});

it('shows the default column on the probes table', function () {
    $user = User::factory()->create();

    $table = Livewire::actingAs($user)
        ->test(ListProbes::class)
        ->instance()
        ->getTable();

    expect($table->getColumn('is_default'))->not->toBeNull();
});
