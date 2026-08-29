<?php

declare(strict_types=1);

use App\Actions\CheckDatabase;
use App\Checking\DatabaseUrl;
use App\Checking\MysqlTransport;
use App\Checking\PostgresTransport;
use App\Checking\RedisTransport;
use App\Checking\SocketOutcome;
use App\Enums\IpFamily;
use App\Models\Monitor;

function fakeMysql(SocketOutcome $outcome, ?object $captured = null): void
{
    app()->instance(MysqlTransport::class, new class($outcome, $captured) implements MysqlTransport
    {
        public function __construct(private readonly SocketOutcome $outcome, private readonly ?object $captured) {}

        public function connect(DatabaseUrl $url, int $timeoutSeconds, IpFamily $family, bool $verifyTls, ?string $command = null, ?string $proxyUrl = null): SocketOutcome
        {
            if ($this->captured !== null) {
                $this->captured->url = $url;
                $this->captured->command = $command;
                $this->captured->proxy = $proxyUrl;
            }

            return $this->outcome;
        }
    });
}

function fakeRedis(SocketOutcome $outcome, ?object $captured = null): void
{
    app()->instance(RedisTransport::class, new class($outcome, $captured) implements RedisTransport
    {
        public function __construct(private readonly SocketOutcome $outcome, private readonly ?object $captured) {}

        public function connect(DatabaseUrl $url, int $timeoutSeconds, IpFamily $family, bool $verifyTls, ?string $command = null, ?string $proxyUrl = null): SocketOutcome
        {
            if ($this->captured !== null) {
                $this->captured->url = $url;
                $this->captured->command = $command;
                $this->captured->proxy = $proxyUrl;
                $this->captured->verifyTls = $verifyTls;
            }

            return $this->outcome;
        }
    });
}

function fakePostgres(SocketOutcome $outcome, ?object $captured = null): void
{
    app()->instance(PostgresTransport::class, new class($outcome, $captured) implements PostgresTransport
    {
        public function __construct(private readonly SocketOutcome $outcome, private readonly ?object $captured) {}

        public function connect(DatabaseUrl $url, int $timeoutSeconds, IpFamily $family, bool $verifyTls, ?string $command = null, ?string $proxyUrl = null): SocketOutcome
        {
            if ($this->captured !== null) {
                $this->captured->url = $url;
                $this->captured->command = $command;
            }

            return $this->outcome;
        }
    });
}

it('passes mysql checks after login and a status payload', function () {
    $monitor = Monitor::factory()->mysql()->withDefaultConditions()->create();
    $monitor->load('conditions');
    $captured = (object) [];

    fakeMysql(SocketOutcome::ok(18, '10.0.0.4', '{"version":"8.4.0","database":"app","tables":["users"]}'), $captured);

    $result = CheckDatabase::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue()
        ->and($result->latencyMs)->toBe(18)
        ->and($result->resolvedIp)->toBe('10.0.0.4')
        ->and($result->responseBody)->toContain('8.4.0')
        ->and($captured->url)->toBeInstanceOf(DatabaseUrl::class)
        ->and($captured->url->host)->toBe('db.example.com')
        ->and($captured->url->user)->toBe('app')
        ->and($captured->url->password)->toBe('secret')
        ->and($captured->url->database)->toBe('app')
        ->and($captured->command)->toBeNull();
});

it('fails mysql checks when login is refused', function () {
    $monitor = Monitor::factory()->mysql()->withDefaultConditions()->create();
    $monitor->load('conditions');

    fakeMysql(SocketOutcome::failed(9, 'Access denied for user'));

    $result = CheckDatabase::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('Access denied for user');
});

it('runs optional mysql sql from the request body', function () {
    $monitor = Monitor::factory()->mysql()->withDefaultConditions()->create([
        'request_body' => 'SHOW TABLES',
    ]);
    $monitor->load('conditions');
    $captured = (object) [];

    fakeMysql(SocketOutcome::ok(6, '127.0.0.1', '{"result":[{"Tables_in_app":"users"}]}'), $captured);

    CheckDatabase::make()->handle($monitor);

    expect($captured->command)->toBe('SHOW TABLES');
});

it('passes redis checks after auth, ping, and info', function () {
    $monitor = Monitor::factory()->redis()->withDefaultConditions()->create();
    $monitor->load('conditions');
    $captured = (object) [];

    fakeRedis(SocketOutcome::ok(7, '10.0.0.8', '{"pong":true,"redis_version":"7.2.4","dbsize":4}'), $captured);

    $result = CheckDatabase::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->responseBody)->toContain('7.2.4')
        ->and($captured->url->password)->toBe('secret')
        ->and($captured->url->database)->toBe('0');
});

it('passes the monitor proxy and tls flag to redis', function () {
    $monitor = Monitor::factory()->redis()->withDefaultConditions()->create([
        'target' => 'rediss://:secret@cache.example.com:6380/0',
        'proxy_url' => 'socks5://127.0.0.1:1080',
        'verify_tls' => false,
    ]);
    $monitor->load('conditions');
    $captured = (object) [];

    fakeRedis(SocketOutcome::ok(4, '127.0.0.1', '{"pong":true}'), $captured);

    CheckDatabase::make()->handle($monitor);

    expect($captured->proxy)->toBe('socks5://127.0.0.1:1080')
        ->and($captured->verifyTls)->toBeFalse()
        ->and($captured->url->usesTls())->toBeTrue();
});

it('runs an optional redis command from the request body', function () {
    $monitor = Monitor::factory()->redis()->withDefaultConditions()->create([
        'request_body' => 'DBSIZE',
    ]);
    $monitor->load('conditions');
    $captured = (object) [];

    fakeRedis(SocketOutcome::ok(3, '127.0.0.1', '{"result":12}'), $captured);

    $result = CheckDatabase::make()->handle($monitor);

    expect($captured->command)->toBe('DBSIZE')
        ->and($result->success)->toBeTrue();
});

it('passes postgres checks after login and a table list', function () {
    $monitor = Monitor::factory()->postgres()->withDefaultConditions()->create();
    $monitor->load('conditions');
    $captured = (object) [];

    fakePostgres(SocketOutcome::ok(11, '10.0.0.5', '{"version":"PostgreSQL 16.4","database":"app","tables":["users"]}'), $captured);

    $result = CheckDatabase::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($captured->url->host)->toBe('db.example.com')
        ->and($captured->url->port)->toBe(5432)
        ->and($captured->command)->toBeNull();
});

it('fails postgres checks when the server is unreachable', function () {
    $monitor = Monitor::factory()->postgres()->withDefaultConditions()->create();
    $monitor->load('conditions');

    fakePostgres(SocketOutcome::failed(5, 'Connection refused'));

    $result = CheckDatabase::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('Connection refused');
});

it('fails database checks when the target is not a connection url', function () {
    $monitor = Monitor::factory()->mysql()->withDefaultConditions()->create([
        'target' => 'db.example.com:3306',
    ]);
    $monitor->load('conditions');

    $result = CheckDatabase::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toContain('mysql://');
});

it('redacts passwords when displaying database targets', function () {
    $monitor = Monitor::factory()->mysql()->make();

    expect($monitor->displayTarget())->toBe('mysql://app:***@db.example.com:3306/app')
        ->and(Monitor::factory()->make()->displayTarget())->toBe('https://example.com/health');
});
