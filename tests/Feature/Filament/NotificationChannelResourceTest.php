<?php

declare(strict_types=1);

use App\Enums\NotificationChannelType;
use App\Filament\Resources\NotificationChannels\Pages\CreateNotificationChannel;
use App\Filament\Resources\NotificationChannels\Pages\EditNotificationChannel;
use App\Filament\Resources\NotificationChannels\Pages\ListNotificationChannels;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Notifications\ChannelTestNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('shows notification channels and a redacted destination', function () {
    $user = User::factory()->create();
    NotificationChannel::factory()->mail('alerts@example.com')->create(['name' => 'Ops mail']);
    NotificationChannel::factory()->slack('https://hooks.slack.com/services/T000/B000/secret')->create([
        'name' => 'Ops Slack',
    ]);

    $this->actingAs($user)
        ->get('/admin/settings/notification-channels')
        ->assertOk();

    Livewire::actingAs($user)
        ->test(ListNotificationChannels::class)
        ->loadTable()
        ->assertSee('Ops mail')
        ->assertSee('alerts@example.com')
        ->assertSee('Ops Slack')
        ->assertSee('hooks.slack.com')
        ->assertDontSee('T000/B000/secret');
});

it('shows only the setup fields the selected type needs', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateNotificationChannel::class)
        ->assertFormFieldIsVisible('config.to')
        ->assertFormFieldIsHidden('config.webhook_url')
        ->assertFormFieldIsHidden('config.url')
        ->assertFormFieldIsHidden('config.routing_key')
        ->set('data.type', NotificationChannelType::Slack->value)
        ->assertFormFieldIsHidden('config.to')
        ->assertFormFieldIsVisible('config.webhook_url')
        ->assertFormFieldIsHidden('config.url')
        ->set('data.type', NotificationChannelType::Webhook->value)
        ->assertFormFieldIsHidden('config.webhook_url')
        ->assertFormFieldIsVisible('config.url')
        ->set('data.type', NotificationChannelType::Pagerduty->value)
        ->assertFormFieldIsHidden('config.url')
        ->assertFormFieldIsVisible('config.routing_key');
});

it('creates an email channel from the type-specific fields', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateNotificationChannel::class)
        ->fillForm([
            'name' => 'Ops email',
            'type' => NotificationChannelType::Mail,
            'config' => [
                'to' => 'ops@example.com',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $channel = NotificationChannel::query()->where('name', 'Ops email')->first();

    expect($channel)->not->toBeNull()
        ->and($channel?->type)->toBe(NotificationChannelType::Mail)
        ->and($channel?->configArray())->toBe(['to' => 'ops@example.com']);
});

it('requires the recipient when creating an email channel', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateNotificationChannel::class)
        ->fillForm([
            'name' => 'Ops email',
            'type' => NotificationChannelType::Mail,
            'config' => ['to' => ''],
        ])
        ->call('create')
        ->assertHasFormErrors(['config.to']);
});

it('creates a slack channel without keeping email config', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateNotificationChannel::class)
        ->fillForm([
            'name' => 'Ops Slack',
            'type' => NotificationChannelType::Slack,
            'config' => [
                'to' => 'ops@example.com',
                'webhook_url' => 'https://hooks.slack.com/services/T/B/xxx',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $channel = NotificationChannel::query()->where('name', 'Ops Slack')->first();

    expect($channel?->configArray())->toBe([
        'webhook_url' => 'https://hooks.slack.com/services/T/B/xxx',
    ]);
});

it('hydrates aliased webhook URLs on edit and saves the canonical key', function () {
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->create([
        'name' => 'Legacy Slack',
        'type' => NotificationChannelType::Slack,
        'config' => ['url' => 'https://hooks.slack.com/services/T/B/legacy'],
    ]);

    Livewire::actingAs($user)
        ->test(EditNotificationChannel::class, ['record' => $channel->getRouteKey()])
        ->assertFormSet([
            'config.webhook_url' => 'https://hooks.slack.com/services/T/B/legacy',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($channel->fresh()?->configArray())->toBe([
        'webhook_url' => 'https://hooks.slack.com/services/T/B/legacy',
    ]);
});

it('lists notification channels in the Filament table', function () {
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->mail()->create(['name' => 'Ops mail']);

    Livewire::actingAs($user)
        ->test(ListNotificationChannels::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$channel]);
});

it('sends a test notification from the edit page', function () {
    Notification::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->mail()->create(['name' => 'Ops mail']);

    Livewire::actingAs($user)
        ->test(EditNotificationChannel::class, ['record' => $channel->getRouteKey()])
        ->assertActionExists('test')
        ->callAction('test')
        ->assertNotified('Test notification sent');

    Notification::assertSentTo($channel, ChannelTestNotification::class);
});

it('sends a test using unsaved form values', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->create([
        'name' => 'Hooks',
        'config' => ['url' => 'https://example.com/hooks/old'],
    ]);

    Livewire::actingAs($user)
        ->test(EditNotificationChannel::class, ['record' => $channel->getRouteKey()])
        ->fillForm([
            'config' => ['url' => 'https://example.com/hooks/new'],
        ])
        ->callAction('test')
        ->assertNotified('Test notification sent');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.com/hooks/new');
    expect($channel->fresh()?->configArray())->toBe(['url' => 'https://example.com/hooks/old']);
});

it('does not send a test when the form is invalid', function () {
    Notification::fake();
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->mail()->create();

    Livewire::actingAs($user)
        ->test(EditNotificationChannel::class, ['record' => $channel->getRouteKey()])
        ->fillForm([
            'config' => ['to' => ''],
        ])
        ->callAction('test')
        ->assertHasFormErrors(['config.to']);

    Notification::assertNothingSent();
});

it('shows a failure notice when the test cannot be delivered', function () {
    Http::fake([
        '*' => Http::response('nope', 500),
    ]);

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->create();

    Livewire::actingAs($user)
        ->test(EditNotificationChannel::class, ['record' => $channel->getRouteKey()])
        ->callAction('test')
        ->assertNotified('Test notification failed');
});
