<?php

declare(strict_types=1);

use App\Models\NotificationChannel;
use App\Models\User;

it('creates a notification channel and canonicalizes config aliases', function () {
    $user = User::factory()->create();

    $channel = graphql('
        mutation ($input: CreateNotificationChannelInput!) {
            createNotificationChannel(input: $input) {
                id
                type
                config { key value }
            }
        }
    ', [
        'input' => [
            'name' => 'Ops Slack',
            'type' => 'Slack',
            'config' => [
                ['key' => 'url', 'value' => 'https://hooks.slack.com/services/T/B/xxx'],
            ],
        ],
    ], $user)->assertSuccessful()
        ->json('data.createNotificationChannel');

    expect($channel['type'])->toBe('Slack')
        ->and($channel['config'])->toBe([
            ['key' => 'webhook_url', 'value' => 'https://hooks.slack.com/services/T/B/xxx'],
        ]);
});

it('rejects a mail channel without a recipient', function () {
    $response = graphql('
        mutation ($input: CreateNotificationChannelInput!) {
            createNotificationChannel(input: $input) { id }
        }
    ', [
        'input' => [
            'name' => 'Ops mail',
            'type' => 'Mail',
            'config' => [],
        ],
    ]);

    expect($response->json('errors.0.message'))->toContain('recipient email')
        ->and($response->json('data.createNotificationChannel'))->toBeNull();
});

it('updates a channel and drops keys the new type does not use', function () {
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->slack()->create();

    $updated = graphql('
        mutation ($id: ID!, $input: UpdateNotificationChannelInput!) {
            updateNotificationChannel(id: $id, input: $input) {
                type
                config { key value }
            }
        }
    ', [
        'id' => $channel->id,
        'input' => [
            'type' => 'Mail',
            'config' => [
                ['key' => 'to', 'value' => 'ops@example.com'],
                ['key' => 'webhook_url', 'value' => 'https://hooks.slack.com/services/T/B/xxx'],
            ],
        ],
    ], $user)->assertSuccessful()
        ->json('data.updateNotificationChannel');

    expect($updated['type'])->toBe('Mail')
        ->and($updated['config'])->toBe([
            ['key' => 'to', 'value' => 'ops@example.com'],
        ]);
});
