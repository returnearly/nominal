<?php

declare(strict_types=1);

use App\Enums\InterfaceAuth;
use App\Filament\Clusters\Settings\Pages\GeneralSettings;
use App\Filament\Resources\NotificationChannels\Pages\ListNotificationChannels;
use App\Filament\Resources\Probes\Pages\ListProbes;
use App\Models\NotificationChannel;
use App\Models\Probe;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it('registers settings in the top navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/monitors')
        ->assertOk()
        ->assertSee('Settings')
        ->assertDontSee('Notification Channels')
        ->assertDontSee('API Tokens');
});

it('moves notification channels and probes under settings', function () {
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->create(['name' => 'PagerDuty prod']);
    $probe = Probe::factory()->create(['name' => 'US East']);

    $this->actingAs($user)
        ->get('/admin/notification-channels')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/admin/probes')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/admin/settings/notification-channels')
        ->assertOk()
        ->assertSee('PagerDuty prod');

    $this->actingAs($user)
        ->get('/admin/settings/probes')
        ->assertOk()
        ->assertSee('US East');

    expect(Route::has('filament.admin.resources.notification-channels.index'))->toBeFalse()
        ->and(Route::has('filament.admin.settings.resources.notification-channels.index'))->toBeTrue()
        ->and(Route::has('filament.admin.settings.resources.probes.index'))->toBeTrue();

    Livewire::actingAs($user)
        ->test(ListNotificationChannels::class)
        ->assertCanSeeTableRecords([$channel]);

    Livewire::actingAs($user)
        ->test(ListProbes::class)
        ->assertCanSeeTableRecords([$probe]);
});

it('lands settings on the general page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/settings')
        ->assertRedirect('/admin/settings/general');
});

it('shows instance configuration on the general page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(GeneralSettings::class)
        ->assertSee('login')
        ->assertSee('local')
        ->assertSee('nominal_')
        ->assertSee('environment configuration')
        ->assertDontSee('operator@nominal.local');
});

it('shows the anonymous operator when interface auth is none', function () {
    config([
        'nominal.interface_auth' => InterfaceAuth::None->value,
        'nominal.probe_region' => 'us-east',
        'nominal.metrics_prefix' => 'custom_',
        'nominal.anonymous_operator.email' => 'op@test.local',
        'nominal.anonymous_operator.name' => 'Test Operator',
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(GeneralSettings::class)
        ->assertSee('none')
        ->assertSee('us-east')
        ->assertSee('custom_')
        ->assertSee('op@test.local')
        ->assertSee('Test Operator');
});
