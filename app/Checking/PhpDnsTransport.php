<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\DnsQueryType;
use App\Enums\IpFamily;
use Throwable;

final class PhpDnsTransport implements DnsTransport
{
    public function query(
        string $resolver,
        string $name,
        DnsQueryType $type,
        int $timeoutSeconds,
        IpFamily $family,
    ): DnsOutcome {
        $address = SocketAddress::parse($resolver, 53);
        $id = random_int(0, 65535);
        $packet = DnsMessage::query($name, $type, $id);
        $started = hrtime(true);

        try {
            $client = @stream_socket_client(
                $address->remote('udp'),
                $errorCode,
                $errorMessage,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                StreamSocket::context($family),
            );
        } catch (Throwable $exception) {
            return DnsOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $exception->getMessage(),
            );
        }

        if ($client === false) {
            return DnsOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $errorMessage !== '' ? $errorMessage : 'DNS connection failed',
            );
        }

        stream_set_timeout($client, $timeoutSeconds);
        fwrite($client, $packet);
        $response = fread($client, 4096);
        $ip = StreamSocket::peerIp($client);
        $timedOut = stream_get_meta_data($client)['timed_out'] ?? false;
        fclose($client);

        $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);

        if ($timedOut || $response === false || $response === '') {
            return DnsOutcome::failed($latencyMs, 'DNS query timed out', $ip);
        }

        try {
            $parsed = DnsMessage::parse($response);
        } catch (Throwable $exception) {
            return DnsOutcome::failed($latencyMs, $exception->getMessage(), $ip);
        }

        return DnsOutcome::ok($latencyMs, $ip, $parsed['rcode'], $parsed['answers']);
    }
}
