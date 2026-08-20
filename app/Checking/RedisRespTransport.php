<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;
use RuntimeException;
use Throwable;

final class RedisRespTransport implements RedisTransport
{
    /**
     * @var list<string>
     */
    private const InfoKeys = [
        'redis_version',
        'redis_mode',
        'os',
        'arch_bits',
        'tcp_port',
        'uptime_in_seconds',
        'connected_clients',
        'used_memory_human',
        'role',
        'cluster_enabled',
    ];

    public function __construct(private StreamDialer $dialer) {}

    public function connect(
        DatabaseUrl $url,
        int $timeoutSeconds,
        IpFamily $family,
        bool $verifyTls,
        ?string $command = null,
        ?string $proxyUrl = null,
    ): SocketOutcome {
        $started = hrtime(true);

        try {
            $client = $this->dialer->connect(
                $url->host,
                $url->port,
                $timeoutSeconds,
                $family,
                $proxyUrl,
                $url->usesTls() ? [
                    'verify_peer' => $verifyTls,
                    'verify_peer_name' => $verifyTls,
                    'peer_name' => $url->host,
                ] : [],
            );
        } catch (Throwable $exception) {
            return SocketOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $exception->getMessage(),
            );
        }

        stream_set_timeout($client, $timeoutSeconds);
        $ip = StreamSocket::peerIp($client);

        try {
            $this->authenticate($client, $url);
            $payload = $this->inspect($client, $command);
        } catch (Throwable $exception) {
            fclose($client);

            return SocketOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $exception->getMessage(),
                $ip,
            );
        }

        fclose($client);

        return SocketOutcome::ok(
            (int) ((hrtime(true) - $started) / 1_000_000),
            $ip,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }

    private function authenticate(mixed $client, DatabaseUrl $url): void
    {
        if ($url->password !== null) {
            if ($url->user !== null) {
                $this->command($client, 'AUTH', $url->user, $url->password);
            } else {
                $this->command($client, 'AUTH', $url->password);
            }
        }

        if ($url->database !== null) {
            $this->command($client, 'SELECT', $url->database);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(mixed $client, ?string $command): array
    {
        if ($command !== null) {
            $tokens = RedisProtocol::tokenize($command);

            if ($tokens === []) {
                throw new RuntimeException('Redis command cannot be empty.');
            }

            return ['result' => $this->command($client, ...$tokens)];
        }

        $pong = $this->command($client, 'PING');
        $info = RedisProtocol::parseInfo((string) $this->command($client, 'INFO', 'server'));
        $dbsize = $this->command($client, 'DBSIZE');

        $payload = [
            'pong' => is_string($pong) && strcasecmp($pong, 'PONG') === 0,
        ];

        foreach (self::InfoKeys as $key) {
            if (array_key_exists($key, $info)) {
                $payload[$key] = $info[$key];
            }
        }

        $payload['dbsize'] = $dbsize;

        return $payload;
    }

    private function command(mixed $client, string ...$arguments): mixed
    {
        $written = fwrite($client, RedisProtocol::encode(...$arguments));

        if ($written === false) {
            throw new RuntimeException('Failed to write Redis command.');
        }

        return RedisProtocol::decode($client);
    }
}
