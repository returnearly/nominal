<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;
use DateTimeImmutable;
use Throwable;

final class PhpStreamTlsTransport implements TlsTransport
{
    public function __construct(private StreamDialer $dialer) {}

    public function connect(
        string $host,
        int $port,
        int $timeoutSeconds,
        IpFamily $family,
        bool $verifyTls,
        ?string $body = null,
        ?string $proxyUrl = null,
    ): SocketOutcome {
        $started = hrtime(true);

        try {
            $client = $this->dialer->connect($host, $port, $timeoutSeconds, $family, $proxyUrl, [
                'capture_peer_cert' => true,
                'verify_peer' => $verifyTls,
                'verify_peer_name' => $verifyTls,
                'peer_name' => $host,
            ]);
        } catch (Throwable $exception) {
            return SocketOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $exception->getMessage(),
            );
        }

        $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);
        $ip = StreamSocket::peerIp($client);
        $expiresAt = self::certificateExpiry($client);
        $response = null;

        if ($body !== null && $body !== '') {
            stream_set_timeout($client, $timeoutSeconds);
            fwrite($client, $body);
            $response = fread($client, 1024);

            if ($response === false) {
                $response = null;
            }
        }

        fclose($client);

        return SocketOutcome::ok($latencyMs, $ip, $response, $expiresAt);
    }

    /**
     * @param  resource  $client
     */
    private static function certificateExpiry(mixed $client): ?DateTimeImmutable
    {
        $params = stream_context_get_params($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($cert === null) {
            return null;
        }

        $parsed = openssl_x509_parse($cert);

        if (! is_array($parsed) || ! isset($parsed['validTo_time_t'])) {
            return null;
        }

        return (new DateTimeImmutable)->setTimestamp((int) $parsed['validTo_time_t']);
    }
}
