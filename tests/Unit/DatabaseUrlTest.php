<?php

declare(strict_types=1);

use App\Checking\DatabaseUrl;
use App\Enums\MonitorType;

it('parses mysql, redis, and postgres connection urls', function (string $target, MonitorType $type, string $host, int $port, ?string $user, ?string $password, ?string $database) {
    $url = DatabaseUrl::parse($target, $type);

    expect($url->host)->toBe($host)
        ->and($url->port)->toBe($port)
        ->and($url->user)->toBe($user)
        ->and($url->password)->toBe($password)
        ->and($url->database)->toBe($database);
})->with([
    ['mysql://app:secret@db.example.com:3306/app', MonitorType::Mysql, 'db.example.com', 3306, 'app', 'secret', 'app'],
    ['mysql://app@127.0.0.1/mysql', MonitorType::Mysql, '127.0.0.1', 3306, 'app', null, 'mysql'],
    ['mariadb://root@localhost:3307/', MonitorType::Mysql, 'localhost', 3307, 'root', null, null],
    ['redis://:cachepass@cache.example.com:6379/0', MonitorType::Redis, 'cache.example.com', 6379, null, 'cachepass', '0'],
    ['rediss://default:acl@cache.example.com/1', MonitorType::Redis, 'cache.example.com', 6380, 'default', 'acl', '1'],
    ['postgres://app:secret@db.example.com:5432/app', MonitorType::Postgres, 'db.example.com', 5432, 'app', 'secret', 'app'],
    ['postgresql://app@db.example.com/app', MonitorType::Postgres, 'db.example.com', 5432, 'app', null, 'app'],
    ['postgres://app:p%40ss@db.example.com/app', MonitorType::Postgres, 'db.example.com', 5432, 'app', 'p@ss', 'app'],
    ['mysql://app:secret@[2001:db8::1]:3306/app', MonitorType::Mysql, '2001:db8::1', 3306, 'app', 'secret', 'app'],
]);

it('enables tls from the scheme or sslmode query', function () {
    expect(DatabaseUrl::parse('rediss://cache.example.com', MonitorType::Redis)->usesTls())->toBeTrue()
        ->and(DatabaseUrl::parse('mysqls://db.example.com', MonitorType::Mysql)->usesTls())->toBeTrue()
        ->and(DatabaseUrl::parse('postgres://db.example.com/app?sslmode=require', MonitorType::Postgres)->usesTls())->toBeTrue()
        ->and(DatabaseUrl::parse('postgres://db.example.com/app', MonitorType::Postgres)->usesTls())->toBeFalse()
        ->and(DatabaseUrl::parse('postgres://db.example.com/app?sslmode=require', MonitorType::Postgres)->sslMode(true))->toBe('require')
        ->and(DatabaseUrl::parse('postgresqls://db.example.com/app', MonitorType::Postgres)->sslMode(true))->toBe('verify-full')
        ->and(DatabaseUrl::parse('postgresqls://db.example.com/app', MonitorType::Postgres)->sslMode(false))->toBe('require');
});

it('redacts passwords in connection urls', function () {
    $url = DatabaseUrl::parse('mysql://app:super-secret@db.example.com:3306/app?charset=utf8mb4', MonitorType::Mysql);

    expect($url->redacted())->toBe('mysql://app:***@db.example.com:3306/app?charset=utf8mb4')
        ->and(DatabaseUrl::parse('redis://:secret@cache.example.com:6379/0', MonitorType::Redis)->redacted())
        ->toBe('redis://:***@cache.example.com:6379/0');
});

it('rejects urls that do not match the monitor type', function () {
    expect(fn () => DatabaseUrl::parse('redis://cache.example.com', MonitorType::Mysql))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => DatabaseUrl::parse('db.example.com:3306', MonitorType::Mysql))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => DatabaseUrl::parse('https://example.com', MonitorType::Http))
        ->toThrow(InvalidArgumentException::class);
});
