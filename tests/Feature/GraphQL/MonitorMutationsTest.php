<?php

declare(strict_types=1);

use App\Models\Probe;
use App\Models\User;

it('rejects unauthenticated GraphQL requests', function () {
    $response = test()->postJson('/graphql', [
        'query' => '{ monitors { id } }',
    ]);

    $response->assertSuccessful();
    expect($response->json('errors.0.message'))->toContain('Unauthenticated');
});

it('creates, updates, and deletes a monitor', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                id
                name
                type
                target
                method
                requestHeaders { key value }
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => 'API health',
            'type' => 'Http',
            'target' => 'https://example.com/health',
            'method' => 'GET',
            'requestHeaders' => [
                ['key' => 'X-Token', 'value' => 'abc'],
            ],
            'conditions' => ['[STATUS] == 200'],
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['name'])->toBe('API health')
        ->and($created['type'])->toBe('Http')
        ->and($created['requestHeaders'][0]['key'])->toBe('X-Token')
        ->and($created['conditions'][0]['expression'])->toBe('[STATUS] == 200');

    $updated = graphql('
        mutation ($id: ID!, $input: UpdateMonitorInput!) {
            updateMonitor(id: $id, input: $input) {
                name
                retention_days
            }
        }
    ', [
        'id' => $created['id'],
        'input' => [
            'name' => 'API health prod',
            'retentionDays' => 14,
        ],
    ])->json('data.updateMonitor');

    expect($updated['name'])->toBe('API health prod')
        ->and($updated['retention_days'])->toBe(14);

    $deleted = graphql('
        mutation ($id: ID!) {
            deleteMonitor(id: $id)
        }
    ', ['id' => $created['id']])->json('data.deleteMonitor');

    expect($deleted)->toBeTrue();
});

it('creates a TCP monitor', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                name
                type
                target
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => 'Postgres',
            'type' => 'Tcp',
            'target' => 'tcp://db.example.com:5432',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['type'])->toBe('Tcp')
        ->and($created['target'])->toBe('tcp://db.example.com:5432')
        ->and($created['conditions'][0]['expression'])->toBe('[CONNECTED] == true');
});

it('creates a DNS monitor', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                type
                target
                dns_query_name
                dns_query_type
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => 'Resolver',
            'type' => 'Dns',
            'target' => '1.1.1.1',
            'dnsQueryName' => 'example.com',
            'dnsQueryType' => 'A',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['type'])->toBe('Dns')
        ->and($created['dns_query_name'])->toBe('example.com')
        ->and($created['dns_query_type'])->toBe('A')
        ->and($created['conditions'][0]['expression'])->toBe('[DNS_RCODE] == NOERROR');
});

it('manages notification channels and syncs them to a monitor', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();

    $monitor = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) { id }
        }
    ', [
        'input' => [
            'name' => 'Synced',
            'type' => 'Http',
            'target' => 'https://example.com',
            'probeIds' => [$probe->id],
        ],
    ], $user)->json('data.createMonitor');

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
            'name' => 'Ops webhook',
            'type' => 'Webhook',
            'config' => [
                ['key' => 'url', 'value' => 'https://example.com/hook'],
            ],
        ],
    ], $user)->json('data.createNotificationChannel');

    expect($channel['type'])->toBe('Webhook');

    $synced = graphql('
        mutation ($monitorId: ID!, $channelIds: [ID!]!) {
            syncMonitorChannels(monitorId: $monitorId, channelIds: $channelIds) {
                notificationChannels { id }
            }
        }
    ', [
        'monitorId' => $monitor['id'],
        'channelIds' => [$channel['id']],
    ], $user)->json('data.syncMonitorChannels.notificationChannels');

    expect($synced)->toHaveCount(1)
        ->and($synced[0]['id'])->toBe($channel['id']);
});
