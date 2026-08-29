<?php

declare(strict_types=1);

use App\Actions\SaveNotificationChannel;
use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use Illuminate\Validation\ValidationException;

it('canonicalizes aliased config keys', function () {
    $channel = SaveNotificationChannel::make()->handle([
        'name' => 'Ops Slack',
        'type' => NotificationChannelType::Slack,
        'config' => [
            'url' => 'https://hooks.slack.com/services/T/B/xxx',
        ],
    ]);

    expect($channel->type)->toBe(NotificationChannelType::Slack)
        ->and($channel->configArray())->toBe([
            'webhook_url' => 'https://hooks.slack.com/services/T/B/xxx',
        ])
        ->and($channel->destination)->toBe('hooks.slack.com');
});

it('rejects mail channels without a recipient', function () {
    SaveNotificationChannel::make()->handle([
        'name' => 'Ops mail',
        'type' => NotificationChannelType::Mail,
        'config' => [],
    ]);
})->throws(ValidationException::class);

it('keeps existing config when it is omitted on update', function () {
    $channel = NotificationChannel::factory()->mail('alerts@example.com')->create();

    $updated = SaveNotificationChannel::make()->handle([
        'name' => 'Renamed mail',
    ], $channel);

    expect($updated->name)->toBe('Renamed mail')
        ->and($updated->configArray())->toBe(['to' => 'alerts@example.com']);
});

it('drops keys the new type does not use', function () {
    $channel = NotificationChannel::factory()->slack()->create();

    $updated = SaveNotificationChannel::make()->handle([
        'type' => NotificationChannelType::Mail,
        'config' => [
            'to' => 'ops@example.com',
            'webhook_url' => 'https://hooks.slack.com/services/T/B/xxx',
        ],
    ], $channel);

    expect($updated->type)->toBe(NotificationChannelType::Mail)
        ->and($updated->configArray())->toBe(['to' => 'ops@example.com']);
});
