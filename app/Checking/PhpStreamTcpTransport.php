<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;
use Throwable;

final class PhpStreamTcpTransport implements TcpTransport
{
    public function connect(
        string $host,
        int $port,
        int $timeoutSeconds,
        IpFamily $family,
        ?string $body = null,
    ): SocketOutcome {
        $address = new SocketAddress($host, $port);
        $started = hrtime(true);

        try {
            $client = @stream_socket_client(
                $address->remote('tcp'),
                $errorCode,
                $errorMessage,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                StreamSocket::context($family),
            );
        } catch (Throwable $exception) {
            return SocketOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $exception->getMessage(),
            );
        }

        $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);

        if ($client === false) {
            return SocketOutcome::failed($latencyMs, $errorMessage !== '' ? $errorMessage : 'TCP connection failed');
        }

        $ip = StreamSocket::peerIp($client);
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

        return SocketOutcome::ok($latencyMs, $ip, $response);
    }
}
