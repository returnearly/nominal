<?php

declare(strict_types=1);

use App\Models\NotificationChannel;
use App\Models\User;

it('creates a slack channel from the typed slack input', function () {
    $user = User::factory()->create();

    $channel = graphql('
        mutation ($input: CreateNotificationChannelInput!) {
            createNotificationChannel(input: $input) {
                id
                type
                mail { to }
                slack { webhookUrl }
                webhook { url }
                pagerduty { routingKey }
            }
        }
    ', [
        'input' => [
            'name' => 'Ops Slack',
            'type' => 'Slack',
            'slack' => [
                'webhookUrl' => 'https://hooks.slack.com/services/T/B/xxx',
            ],
        ],
    ], $user)->assertSuccessful()
        ->json('data.createNotificationChannel');

    expect($channel['type'])->toBe('Slack')
        ->and($channel['slack'])->toBe([
            'webhookUrl' => 'https://hooks.slack.com/services/T/B/xxx',
        ])
        ->and($channel['mail'])->toBeNull()
        ->and($channel['webhook'])->toBeNull()
        ->and($channel['pagerduty'])->toBeNull();

    expect(NotificationChannel::query()->find($channel['id'])?->configArray())->toBe([
        'webhook_url' => 'https://hooks.slack.com/services/T/B/xxx',
    ]);
});

it('creates each channel type from its matching input', function (string $type, array $input, array $expected) {
    $created = graphql('
        mutation ($input: CreateNotificationChannelInput!) {
            createNotificationChannel(input: $input) {
                type
                mail { to }
                slack { webhookUrl }
                microsoftTeams { webhookUrl }
                discord { webhookUrl }
                webhook { url }
                pagerduty { routingKey }
            }
        }
    ', [
        'input' => [
            'name' => $type.' channel',
            'type' => $type,
            ...$input,
        ],
    ])->assertSuccessful()
        ->json('data.createNotificationChannel');

    expect($created['type'])->toBe($type);

    foreach ($expected as $field => $value) {
        expect($created[$field])->toBe($value);
    }
})->with([
    ['Mail', ['mail' => ['to' => 'ops@example.com']], ['mail' => ['to' => 'ops@example.com']]],
    ['Slack', ['slack' => ['webhookUrl' => 'https://hooks.slack.com/services/T/B/xxx']], ['slack' => ['webhookUrl' => 'https://hooks.slack.com/services/T/B/xxx']]],
    ['MicrosoftTeams', ['microsoftTeams' => ['webhookUrl' => 'https://outlook.office.com/webhook/abc']], ['microsoftTeams' => ['webhookUrl' => 'https://outlook.office.com/webhook/abc']]],
    ['Discord', ['discord' => ['webhookUrl' => 'https://discord.com/api/webhooks/1/abc']], ['discord' => ['webhookUrl' => 'https://discord.com/api/webhooks/1/abc']]],
    ['Webhook', ['webhook' => ['url' => 'https://example.com/hooks/nominal']], ['webhook' => ['url' => 'https://example.com/hooks/nominal']]],
    ['Pagerduty', ['pagerduty' => ['routingKey' => 'R0123456789ABCDEF']], ['pagerduty' => ['routingKey' => 'R0123456789ABCDEF']]],
]);

it('rejects a mail channel without a mail input', function () {
    $response = graphql('
        mutation ($input: CreateNotificationChannelInput!) {
            createNotificationChannel(input: $input) { id }
        }
    ', [
        'input' => [
            'name' => 'Ops mail',
            'type' => 'Mail',
        ],
    ]);

    expect($response->json('errors.0.message'))->toContain('mail')
        ->and($response->json('data.createNotificationChannel'))->toBeNull();
});

it('rejects an input that does not match the selected type', function () {
    $response = graphql('
        mutation ($input: CreateNotificationChannelInput!) {
            createNotificationChannel(input: $input) { id }
        }
    ', [
        'input' => [
            'name' => 'Ops Slack',
            'type' => 'Slack',
            'mail' => [
                'to' => 'ops@example.com',
            ],
        ],
    ]);

    expect($response->json('errors.0.message'))->toContain('mail')
        ->and($response->json('errors.0.message'))->toContain('Slack')
        ->and($response->json('data.createNotificationChannel'))->toBeNull();
});

it('keeps existing config when only the name is updated', function () {
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->mail('alerts@example.com')->create();

    $updated = graphql('
        mutation ($id: ID!, $input: UpdateNotificationChannelInput!) {
            updateNotificationChannel(id: $id, input: $input) {
                name
                type
                mail { to }
            }
        }
    ', [
        'id' => $channel->id,
        'input' => [
            'name' => 'Renamed mail',
        ],
    ], $user)->assertSuccessful()
        ->json('data.updateNotificationChannel');

    expect($updated['name'])->toBe('Renamed mail')
        ->and($updated['type'])->toBe('Mail')
        ->and($updated['mail'])->toBe(['to' => 'alerts@example.com']);
});

it('updates a channel to a new type and drops the old typed field', function () {
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->slack()->create();

    $updated = graphql('
        mutation ($id: ID!, $input: UpdateNotificationChannelInput!) {
            updateNotificationChannel(id: $id, input: $input) {
                type
                mail { to }
                slack { webhookUrl }
            }
        }
    ', [
        'id' => $channel->id,
        'input' => [
            'type' => 'Mail',
            'mail' => [
                'to' => 'ops@example.com',
            ],
        ],
    ], $user)->assertSuccessful()
        ->json('data.updateNotificationChannel');

    expect($updated['type'])->toBe('Mail')
        ->and($updated['mail'])->toBe(['to' => 'ops@example.com'])
        ->and($updated['slack'])->toBeNull();

    expect($channel->fresh()?->configArray())->toBe([
        'to' => 'ops@example.com',
    ]);
});

it('rejects changing type without the new type input', function () {
    $channel = NotificationChannel::factory()->slack()->create();

    $response = graphql('
        mutation ($id: ID!, $input: UpdateNotificationChannelInput!) {
            updateNotificationChannel(id: $id, input: $input) { id }
        }
    ', [
        'id' => $channel->id,
        'input' => [
            'type' => 'Mail',
        ],
    ]);

    expect($response->json('errors.0.message'))->toContain('mail')
        ->and($response->json('data.updateNotificationChannel'))->toBeNull();
});
