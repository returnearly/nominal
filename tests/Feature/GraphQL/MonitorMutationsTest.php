<?php

declare(strict_types=1);

use App\Jobs\RunCheckJob;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('rejects unauthenticated GraphQL requests', function () {
    $response = test()->postJson('/graphql', [
        'query' => '{ monitors { id } }',
    ]);

    $response->assertSuccessful();
    expect($response->json('errors.0.message'))->toContain('Unauthenticated');
});

it('attaches default probes when probe ids are omitted', function () {
    $default = Probe::factory()->asDefault()->create(['slug' => 'local', 'queue' => 'checks.local']);
    Probe::factory()->create(['slug' => 'eu', 'queue' => 'checks.eu']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                probes { id is_default }
            }
        }
    ', [
        'input' => [
            'name' => 'API health',
            'type' => 'Http',
            'target' => 'https://example.com/health',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor.probes');

    expect($created)->toHaveCount(1)
        ->and($created[0]['id'])->toBe($default->id)
        ->and($created[0]['is_default'])->toBeTrue();
});

it('creates, updates, and deletes a monitor', function () {
    Probe::factory()->asDefault()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                id
                name
                type
                target
                method
                requestHeaders { key value }
                proxy_url
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
            'proxyUrl' => 'socks5h://127.0.0.1:1080',
            'conditions' => ['[STATUS] == 200'],
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['name'])->toBe('API health')
        ->and($created['type'])->toBe('Http')
        ->and($created['requestHeaders'][0]['key'])->toBe('X-Token')
        ->and($created['proxy_url'])->toBe('socks5h://127.0.0.1:1080')
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
    Probe::factory()->asDefault()->create(['slug' => 'local', 'queue' => 'checks.local']);

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

it('ignores HTTP-only fields on ping monitors', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                type
                method
                requestHeaders { key value }
                request_body
                follow_redirects
                verify_tls
                proxy_url
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => 'Gateway ping',
            'type' => 'Ping',
            'target' => '1.1.1.1',
            'method' => 'POST',
            'requestBody' => 'ignored',
            'requestHeaders' => [
                ['key' => 'X-Token', 'value' => 'abc'],
            ],
            'followRedirects' => false,
            'verifyTls' => false,
            'proxyUrl' => 'http://proxy.example:8080',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['type'])->toBe('Ping')
        ->and($created['method'])->toBeNull()
        ->and($created['requestHeaders'])->toBe([])
        ->and($created['request_body'])->toBeNull()
        ->and($created['follow_redirects'])->toBeTrue()
        ->and($created['verify_tls'])->toBeTrue()
        ->and($created['proxy_url'])->toBeNull()
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

it('creates a TLS monitor', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                type
                target
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => 'DB TLS',
            'type' => 'Tls',
            'target' => 'tls://db.example.com:5432',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['type'])->toBe('Tls')
        ->and($created['target'])->toBe('tls://db.example.com:5432')
        ->and($created['conditions'][0]['expression'])->toBe('[CONNECTED] == true')
        ->and($created['conditions'][1]['expression'])->toBe('[CERTIFICATE_EXPIRATION] > 48h');
});

it('creates a monitor with a domain expiration condition', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                type
                interval_seconds
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => 'example.com domain',
            'type' => 'Http',
            'target' => 'https://example.com',
            'intervalSeconds' => 3600,
            'conditions' => ['[DOMAIN_EXPIRATION] > 720h'],
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['interval_seconds'])->toBe(3600)
        ->and($created['conditions'][0]['expression'])->toBe('[DOMAIN_EXPIRATION] > 720h');
});

it('rejects domain expiration monitors with an interval under 5 minutes', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $response = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) { id }
        }
    ', [
        'input' => [
            'name' => 'example.com domain',
            'type' => 'Http',
            'target' => 'https://example.com',
            'intervalSeconds' => 60,
            'conditions' => ['[DOMAIN_EXPIRATION] > 720h'],
        ],
    ]);

    expect($response->json('errors.0.message'))->toContain('[DOMAIN_EXPIRATION]')
        ->and($response->json('data.createMonitor'))->toBeNull();
});

it('creates a heartbeat monitor without probes, IP family, or conditions', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                type
                target
                ip_family
                heartbeat_token
                heartbeatUrl
                heartbeatStartUrl
                heartbeatFinishUrl
                heartbeatErrorUrl
                conditions { expression }
                probes { id }
            }
        }
    ', [
        'input' => [
            'name' => 'Nightly backup',
            'type' => 'Heartbeat',
            'target' => 'backup-job',
            'ipFamily' => 'Ipv6',
            'conditions' => ['[CONNECTED] == true'],
            'probeIds' => [Probe::query()->value('id')],
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['type'])->toBe('Heartbeat')
        ->and($created['ip_family'])->toBe('Any')
        ->and($created['heartbeat_token'])->toHaveLength(48)
        ->and($created['heartbeatUrl'])->toEndWith('/api/heartbeat/'.$created['heartbeat_token'])
        ->and($created['heartbeatStartUrl'])->toEndWith('/api/heartbeat/'.$created['heartbeat_token'].'/start')
        ->and($created['heartbeatFinishUrl'])->toEndWith('/api/heartbeat/'.$created['heartbeat_token'].'/finish')
        ->and($created['heartbeatErrorUrl'])->toEndWith('/api/heartbeat/'.$created['heartbeat_token'].'/error')
        ->and($created['conditions'])->toBe([])
        ->and($created['probes'])->toBe([]);
});

it('creates a UDP monitor', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                type
                target
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => 'DNS UDP',
            'type' => 'Udp',
            'target' => 'udp://1.1.1.1:53',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['type'])->toBe('Udp')
        ->and($created['target'])->toBe('udp://1.1.1.1:53')
        ->and($created['conditions'][0]['expression'])->toBe('[CONNECTED] == true');
});

it('creates a WebSocket monitor', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                type
                target
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => 'Live socket',
            'type' => 'WebSocket',
            'target' => 'wss://example.com/socket',
            'requestBody' => 'ping',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['type'])->toBe('WebSocket')
        ->and($created['target'])->toBe('wss://example.com/socket')
        ->and($created['conditions'][0]['expression'])->toBe('[CONNECTED] == true');
});

it('creates mysql, redis, and postgres monitors from connection urls', function (string $type, string $target) {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                type
                target
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => $type.' primary',
            'type' => $type,
            'target' => $target,
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['type'])->toBe($type)
        ->and($created['target'])->toBe($target)
        ->and($created['conditions'][0]['expression'])->toBe('[CONNECTED] == true')
        ->and($created['conditions'][1]['expression'])->toBe('[RESPONSE_TIME] < 50');
})->with([
    ['Mysql', 'mysql://app:secret@db.example.com:3306/app'],
    ['Redis', 'redis://:secret@cache.example.com:6379/0'],
    ['Postgres', 'postgres://app:secret@db.example.com:5432/app'],
]);

it('creates a GraphQL monitor and defaults to POST', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                type
                target
                method
                request_body
                conditions { expression }
            }
        }
    ', [
        'input' => [
            'name' => 'Countries API',
            'type' => 'GraphQL',
            'target' => 'https://countries.trevorblades.com/',
            'requestBody' => '{ __typename }',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['type'])->toBe('GraphQL')
        ->and($created['target'])->toBe('https://countries.trevorblades.com/')
        ->and($created['method'])->toBe('Post')
        ->and($created['request_body'])->toBe('{ __typename }')
        ->and($created['conditions'][0]['expression'])->toBe('[STATUS] >= 200')
        ->and($created['conditions'][1]['expression'])->toBe('[STATUS] <= 299');
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
                webhook { url }
            }
        }
    ', [
        'input' => [
            'name' => 'Ops webhook',
            'type' => 'Webhook',
            'webhook' => [
                'url' => 'https://example.com/hook',
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

it('saves tags and a description on a monitor', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) {
                id
                description
                tags
            }
        }
    ', [
        'input' => [
            'name' => 'Checkout API',
            'description' => "Owned by payments.\nIf this fails, check Redis and the card processor.",
            'tags' => [' Payments ', 'prod', 'payments'],
            'type' => 'Http',
            'target' => 'https://pay.example/health',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    expect($created['description'])->toBe("Owned by payments.\nIf this fails, check Redis and the card processor.")
        ->and($created['tags'])->toBe(['Payments', 'prod']);

    $updated = graphql('
        mutation ($id: ID!, $input: UpdateMonitorInput!) {
            updateMonitor(id: $id, input: $input) {
                description
                tags
            }
        }
    ', [
        'id' => $created['id'],
        'input' => [
            'description' => null,
            'tags' => ['critical'],
        ],
    ])->json('data.updateMonitor');

    expect($updated['description'])->toBeNull()
        ->and($updated['tags'])->toBe(['critical']);
});

it('queues a check when a monitor is created or updated', function () {
    $probe = Probe::factory()->asDefault()->create(['slug' => 'local', 'queue' => 'checks.local']);

    $created = graphql('
        mutation ($input: CreateMonitorInput!) {
            createMonitor(input: $input) { id }
        }
    ', [
        'input' => [
            'name' => 'API health',
            'type' => 'Http',
            'target' => 'https://example.com/health',
        ],
    ])->assertSuccessful()
        ->json('data.createMonitor');

    Queue::assertPushedOn('checks.local', function (RunCheckJob $job) use ($created, $probe): bool {
        return $job->monitorId === $created['id'] && $job->probeId === $probe->id;
    });

    Queue::fake();

    graphql('
        mutation ($id: ID!, $input: UpdateMonitorInput!) {
            updateMonitor(id: $id, input: $input) { id }
        }
    ', [
        'id' => $created['id'],
        'input' => [
            'target' => 'https://example.com/v2',
        ],
    ])->assertSuccessful();

    Queue::assertPushedOn('checks.local', function (RunCheckJob $job) use ($created, $probe): bool {
        return $job->monitorId === $created['id'] && $job->probeId === $probe->id;
    });
});

it('filters monitors by tag', function () {
    Probe::factory()->create(['slug' => 'local', 'queue' => 'checks.local']);

    Monitor::factory()->create(['name' => 'Pay API', 'tags' => ['prod', 'critical']]);
    Monitor::factory()->create(['name' => 'Docs', 'tags' => ['prod']]);
    Monitor::factory()->create(['name' => 'Nightly', 'tags' => ['jobs']]);

    $byTag = graphql('
        query ($tag: String) {
            monitors(tag: $tag) { name }
        }
    ', ['tag' => 'prod'])->json('data.monitors');

    expect(collect($byTag)->pluck('name')->sort()->values()->all())->toBe(['Docs', 'Pay API']);
});
