<?php

declare(strict_types=1);

use App\Enums\MonitorType;
use App\Enums\NotificationChannelType;
use App\Filament\Resources\Monitors\Pages\CreateMonitor;
use App\Filament\Resources\Monitors\Pages\EditMonitor;
use App\Filament\Resources\Monitors\Pages\ListMonitors;
use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Filament\Resources\NotificationChannels\Pages\CreateNotificationChannel;
use App\Filament\Resources\NotificationChannels\Pages\EditNotificationChannel;
use App\Filament\Resources\NotificationChannels\Pages\ListNotificationChannels;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Probe;
use App\Models\User;
use App\Notifications\ChannelTestNotification;
use App\Support\ApiManaged;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    config(['nominal.api_managed' => true]);
});

it('explains that monitors are managed through the API', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertSee(ApiManaged::message())
        ->assertActionDisabled('create');
});

it('refuses creating a monitor from the admin', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateMonitor::class)
        ->assertSee(ApiManaged::message())
        ->assertFormFieldIsDisabled('name')
        ->set('data.name', 'Terraform only')
        ->set('data.type', MonitorType::Http->value)
        ->set('data.target', 'https://example.com/health')
        ->set('data.probes', [$probe->id])
        ->call('create')
        ->assertForbidden();

    expect(Monitor::query()->where('name', 'Terraform only')->exists())->toBeFalse();
});

it('refuses saving and deleting a monitor from the admin', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['name' => 'Kept by API']);

    Livewire::actingAs($user)
        ->test(EditMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertSee(ApiManaged::message())
        ->assertFormFieldIsDisabled('name')
        ->assertActionDisabled('delete')
        ->assertActionDisabled('duplicate')
        ->set('data.name', 'Changed in admin')
        ->call('save')
        ->assertForbidden();

    expect($monitor->fresh()->name)->toBe('Kept by API')
        ->and($monitor->fresh())->not->toBeNull();
});

it('disables duplicate on the monitor view', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create();

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertActionDisabled('duplicate')
        ->assertActionEnabled('edit');
});

it('pauses and resumes a monitor from the view', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['enabled' => true]);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertActionVisible('pause')
        ->assertActionHidden('resume')
        ->callAction('pause')
        ->assertNotified('Monitor paused')
        ->assertActionHidden('pause')
        ->assertActionVisible('resume');

    expect($monitor->fresh()->enabled)->toBeFalse();

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->callAction('resume')
        ->assertNotified('Monitor resumed');

    expect($monitor->fresh()->enabled)->toBeTrue();
});

it('starts maintenance while resources are API-managed', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create();

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertActionEnabled('startMaintenance')
        ->callAction('startMaintenance', [
            'title' => 'Patching',
            'message' => 'Restarting the API.',
        ])
        ->assertNotified('Maintenance started');

    expect($monitor->fresh()->isUnderMaintenance())->toBeTrue();
});

it('explains that notification channels are managed through the API', function () {
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->mail()->create(['name' => 'Ops mail']);

    Livewire::actingAs($user)
        ->test(ListNotificationChannels::class)
        ->loadTable()
        ->assertSee(ApiManaged::message())
        ->assertActionDisabled('create')
        ->assertActionEnabled(TestAction::make('edit')->table($channel))
        ->assertActionDisabled(TestAction::make('delete')->table($channel))
        ->assertActionDisabled(TestAction::make('delete')->table()->bulk());
});

it('refuses creating a notification channel from the admin', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateNotificationChannel::class)
        ->assertSee(ApiManaged::message())
        ->assertFormFieldIsDisabled('name')
        ->fillForm([
            'name' => 'Terraform Slack',
            'type' => NotificationChannelType::Slack,
            'config' => [
                'webhook_url' => 'https://hooks.slack.com/services/T/B/xxx',
            ],
        ])
        ->call('create')
        ->assertForbidden();

    expect(NotificationChannel::query()->where('name', 'Terraform Slack')->exists())->toBeFalse();
});

it('refuses saving and deleting a notification channel from the admin', function () {
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->mail('alerts@example.com')->create(['name' => 'Ops mail']);

    Livewire::actingAs($user)
        ->test(EditNotificationChannel::class, ['record' => $channel->getRouteKey()])
        ->assertSee(ApiManaged::message())
        ->assertFormFieldIsDisabled('name')
        ->assertActionDisabled('delete')
        ->assertActionEnabled('test')
        ->set('data.name', 'Changed in admin')
        ->call('save')
        ->assertForbidden();

    expect($channel->fresh()->name)->toBe('Ops mail')
        ->and($channel->fresh())->not->toBeNull();
});

it('still sends a test notification when resources are API-managed', function () {
    Notification::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->mail()->create();

    Livewire::actingAs($user)
        ->test(EditNotificationChannel::class, ['record' => $channel->getRouteKey()])
        ->callAction('test')
        ->assertNotified('Test notification sent');

    Notification::assertSentTo($channel, ChannelTestNotification::class);
});

it('still creates a monitor through GraphQL when resources are API-managed', function () {
    Probe::factory()->asDefault()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                id
                name
            }
        }
    ', [
        'input' => [
            'name' => 'API health',
            'type' => 'Http',
            'target' => 'https://example.com/health',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['name'])->toBe('API health')
        ->and(Monitor::query()->whereKey($created['id'])->exists())->toBeTrue();
});

it('hides the API-managed notice when the mode is off', function () {
    config(['nominal.api_managed' => false]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ListMonitors::class)
        ->assertDontSee(ApiManaged::message())
        ->assertActionEnabled('create');
});
