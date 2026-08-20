<?php

declare(strict_types=1);

use App\Actions\CheckHttp;
use App\Checking\DomainExpirationReader;
use App\Checking\TlsCertificateReader;
use App\Models\Monitor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;

function fakeCertificates(?DateTimeImmutable $expiresAt = null): TlsCertificateReader
{
    return new class($expiresAt) implements TlsCertificateReader
    {
        public function __construct(private ?DateTimeImmutable $expiresAt) {}

        public function expiresAt(string $host, int $port, int $timeoutSeconds, ?string $proxyUrl = null): ?DateTimeImmutable
        {
            return $this->expiresAt;
        }
    };
}

function fakeDomainExpiration(?DateTimeImmutable $expiresAt = null): DomainExpirationReader
{
    return new class($expiresAt) implements DomainExpirationReader
    {
        public int $calls = 0;

        public function __construct(private ?DateTimeImmutable $expiresAt) {}

        public function expiresAt(string $hostname, int $timeoutSeconds = 10): ?DateTimeImmutable
        {
            $this->calls++;

            return $this->expiresAt;
        }
    };
}

function checkHttp(array $responses, ?DateTimeImmutable $cert = null, array &$history = [], ?DomainExpirationReader $domains = null): CheckHttp
{
    app()->instance(TlsCertificateReader::class, fakeCertificates($cert));
    app()->instance(DomainExpirationReader::class, $domains ?? fakeDomainExpiration());

    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    app()->instance(Client::class, new Client([
        'handler' => $stack,
    ]));

    return CheckHttp::make();
}

it('passes HTTP checks when conditions match', function () {
    $history = [];
    $monitor = Monitor::factory()->withDefaultConditions()->create([
        'target' => 'https://example.com/health',
        'request_headers' => ['X-Token' => 'secret'],
        'request_body' => '{"ping":true}',
        'method' => 'POST',
    ]);
    $monitor->load('conditions');

    $result = checkHttp([
        new Response(200, [], '{"status":"UP"}'),
    ], new DateTimeImmutable('+60 days'), $history)->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->httpStatus)->toBe(200)
        ->and($result->connected)->toBeTrue()
        ->and((string) $history[0]['request']->getBody())->toBe('{"ping":true}');
});

it('fails HTTP checks when a condition fails', function () {
    $monitor = Monitor::factory()->withDefaultConditions()->create();
    $monitor->load('conditions');

    $result = checkHttp([
        new Response(500, [], 'nope'),
    ])->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->httpStatus)->toBe(500)
        ->and($result->message)->toContain('[STATUS] <= 299');
});

it('sends a custom method and treats connection errors as failed conditions', function () {
    $monitor = Monitor::factory()->create();
    $monitor->conditions()->create(['expression' => '[CONNECTED] == true', 'sort' => 0]);
    $monitor->load('conditions');

    $result = checkHttp([
        new ConnectException(
            'Could not connect',
            new Request('GET', 'https://example.com/health'),
        ),
    ])->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse();
});

it('wraps GraphQL query bodies as JSON', function () {
    $history = [];
    $monitor = Monitor::factory()->graphql()->withDefaultConditions()->create([
        'request_body' => '{ user { id } }',
    ]);
    $monitor->load('conditions');

    $result = checkHttp([
        new Response(200, [], '{"data":{"user":{"id":"1"}}}'),
    ], null, $history)->handle($monitor);

    $request = $history[0]['request'];

    expect($result->success)->toBeTrue()
        ->and($request->getMethod())->toBe('POST')
        ->and((string) $request->getBody())->toBe('{"query":"{ user { id } }"}')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');
});

it('does not override an existing GraphQL Content-Type header', function () {
    $history = [];
    $monitor = Monitor::factory()->graphql()->withDefaultConditions()->create([
        'request_headers' => ['Content-Type' => 'application/graphql'],
        'request_body' => '{ __typename }',
    ]);
    $monitor->load('conditions');

    checkHttp([
        new Response(200, [], '{"data":{"__typename":"Query"}}'),
    ], null, $history)->handle($monitor);

    expect($history[0]['request']->getHeaderLine('Content-Type'))->toBe('application/graphql');
});

it('sends HTTP checks through the monitor proxy', function () {
    $container = [];
    $history = Middleware::history($container);
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, [], '{"ok":true}'),
    ]));
    $stack->push($history);

    app()->instance(TlsCertificateReader::class, fakeCertificates());
    app()->instance(Client::class, new Client(['handler' => $stack]));

    $monitor = Monitor::factory()->withDefaultConditions()->create([
        'proxy_url' => 'socks5h://127.0.0.1:1080',
    ]);
    $monitor->load('conditions');

    CheckHttp::make()->handle($monitor);

    expect($container)->toHaveCount(1)
        ->and($container[0]['options'][RequestOptions::PROXY])->toBe('socks5h://127.0.0.1:1080');
});

it('falls back to HTTP_PROXY when the monitor has no proxy', function () {
    config()->set('nominal.proxy.http', 'http://env-proxy:8080');
    config()->set('nominal.proxy.https', 'http://env-proxy:8080');

    $container = [];
    $history = Middleware::history($container);
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, [], '{"ok":true}'),
    ]));
    $stack->push($history);

    app()->instance(TlsCertificateReader::class, fakeCertificates());
    app()->instance(Client::class, new Client(['handler' => $stack]));

    $monitor = Monitor::factory()->withDefaultConditions()->create();
    $monitor->load('conditions');

    CheckHttp::make()->handle($monitor);

    expect($container[0]['options'][RequestOptions::PROXY])->toBe([
        'http' => 'http://env-proxy:8080',
        'https' => 'http://env-proxy:8080',
    ]);
});

it('evaluates domain expiration on HTTP checks', function () {
    $history = [];
    $domains = fakeDomainExpiration(new DateTimeImmutable('+400 days'));
    $monitor = Monitor::factory()->create([
        'interval_seconds' => 3600,
    ]);
    $monitor->conditions()->create(['expression' => '[DOMAIN_EXPIRATION] > 720h', 'sort' => 0]);
    $monitor->load('conditions');

    $result = checkHttp([
        new Response(200, [], 'ok'),
    ], null, $history, $domains)->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->domainExpiresAt)->not->toBeNull()
        ->and($domains->calls)->toBe(1);
});

it('fails HTTP checks when the domain expires too soon', function () {
    $history = [];
    $monitor = Monitor::factory()->create(['interval_seconds' => 3600]);
    $monitor->conditions()->create(['expression' => '[DOMAIN_EXPIRATION] > 720h', 'sort' => 0]);
    $monitor->load('conditions');

    $result = checkHttp([
        new Response(200, [], 'ok'),
    ], null, $history, fakeDomainExpiration(new DateTimeImmutable('+2 days')))->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('[DOMAIN_EXPIRATION]');
});

it('does not look up domain expiration unless a condition asks for it', function () {
    $history = [];
    $domains = fakeDomainExpiration(new DateTimeImmutable('+400 days'));
    $monitor = Monitor::factory()->withDefaultConditions()->create();
    $monitor->load('conditions');

    checkHttp([
        new Response(200, [], '{"status":"UP"}'),
    ], null, $history, $domains)->handle($monitor);

    expect($domains->calls)->toBe(0);
});
