<?php

declare(strict_types=1);

use App\Checking\RedisProtocol;

it('encodes redis commands as RESP arrays', function () {
    expect(RedisProtocol::encode('PING'))->toBe("*1\r\n$4\r\nPING\r\n")
        ->and(RedisProtocol::encode('AUTH', 'secret'))->toBe("*2\r\n$4\r\nAUTH\r\n$6\r\nsecret\r\n")
        ->and(RedisProtocol::encode('AUTH', 'default', 'secret'))->toBe("*3\r\n$4\r\nAUTH\r\n$7\r\ndefault\r\n$6\r\nsecret\r\n");
});

it('decodes simple strings, integers, bulk strings, and arrays', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "+PONG\r\n:12\r\n$5\r\nhello\r\n*2\r\n$3\r\nfoo\r\n$3\r\nbar\r\n$-1\r\n");
    rewind($stream);

    expect(RedisProtocol::decode($stream))->toBe('PONG')
        ->and(RedisProtocol::decode($stream))->toBe(12)
        ->and(RedisProtocol::decode($stream))->toBe('hello')
        ->and(RedisProtocol::decode($stream))->toBe(['foo', 'bar'])
        ->and(RedisProtocol::decode($stream))->toBeNull();

    fclose($stream);
});

it('throws when redis returns an error', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "-NOAUTH Authentication required.\r\n");
    rewind($stream);

    expect(fn () => RedisProtocol::decode($stream))
        ->toThrow(RuntimeException::class, 'NOAUTH Authentication required.');

    fclose($stream);
});

it('parses INFO payloads and tokenizes commands', function () {
    $info = "# Server\r\nredis_version:7.2.4\r\nredis_mode:standalone\r\n\r\n# Clients\r\nconnected_clients:3\r\n";

    expect(RedisProtocol::parseInfo($info))->toBe([
        'redis_version' => '7.2.4',
        'redis_mode' => 'standalone',
        'connected_clients' => '3',
    ])
        ->and(RedisProtocol::tokenize('INFO server'))->toBe(['INFO', 'server'])
        ->and(RedisProtocol::tokenize('SET key "hello world"'))->toBe(['SET', 'key', 'hello world']);
});
