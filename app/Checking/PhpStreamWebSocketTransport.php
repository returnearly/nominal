<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;
use Throwable;

final class PhpStreamWebSocketTransport implements WebSocketTransport
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /**
     * @param  array<string, string>  $headers
     */
    public function connect(
        string $host,
        int $port,
        string $path,
        bool $secure,
        int $timeoutSeconds,
        IpFamily $family,
        bool $verifyTls,
        array $headers,
        ?string $body = null,
    ): SocketOutcome {
        $address = new SocketAddress($host, $port);
        $started = hrtime(true);

        try {
            $client = @stream_socket_client(
                $address->remote($secure ? 'ssl' : 'tcp'),
                $errorCode,
                $errorMessage,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                StreamSocket::context($family, $secure ? [
                    'ssl' => [
                        'verify_peer' => $verifyTls,
                        'verify_peer_name' => $verifyTls,
                        'peer_name' => $host,
                    ],
                ] : []),
            );
        } catch (Throwable $exception) {
            return SocketOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $exception->getMessage(),
            );
        }

        if ($client === false) {
            return SocketOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $errorMessage !== '' ? $errorMessage : 'WebSocket connection failed',
            );
        }

        stream_set_timeout($client, $timeoutSeconds);
        $ip = StreamSocket::peerIp($client);

        try {
            $this->upgrade($client, $host, $port, $path === '' ? '/' : $path, $secure, $headers);
            $response = null;

            if ($body !== null && $body !== '') {
                fwrite($client, self::encodeText($body));
                $response = self::readText($client);
            }
        } catch (Throwable $exception) {
            fclose($client);

            return SocketOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $exception->getMessage(),
                $ip,
            );
        }

        fclose($client);

        return SocketOutcome::ok((int) ((hrtime(true) - $started) / 1_000_000), $ip, $response);
    }

    /**
     * @param  resource  $client
     * @param  array<string, string>  $headers
     */
    private function upgrade(mixed $client, string $host, int $port, string $path, bool $secure, array $headers): void
    {
        $key = base64_encode(random_bytes(16));
        $hostHeader = $host.($this->isDefaultPort($port, $secure) ? '' : ':'.$port);
        $lines = [
            "GET {$path} HTTP/1.1",
            "Host: {$hostHeader}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Key: {$key}",
            'Sec-WebSocket-Version: 13',
            'Origin: http://localhost',
        ];

        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        fwrite($client, implode("\r\n", $lines)."\r\n\r\n");
        $raw = $this->readHeaders($client);

        if (! preg_match('/^HTTP\/\d(?:\.\d)? 101\b/i', $raw)) {
            throw new \RuntimeException('WebSocket upgrade failed.');
        }

        if (preg_match('/Sec-WebSocket-Accept:\s*(\S+)/i', $raw, $matches) !== 1) {
            throw new \RuntimeException('WebSocket accept header missing.');
        }

        $expected = base64_encode(sha1($key.self::GUID, true));

        if (! hash_equals($expected, $matches[1])) {
            throw new \RuntimeException('WebSocket accept header mismatch.');
        }
    }

    /**
     * @param  resource  $client
     */
    private function readHeaders(mixed $client): string
    {
        $raw = '';

        while (! feof($client) && ! str_contains($raw, "\r\n\r\n")) {
            $chunk = fread($client, 1024);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $raw .= $chunk;
        }

        return $raw;
    }

    private static function encodeText(string $payload): string
    {
        $length = strlen($payload);
        $mask = random_bytes(4);
        $frame = chr(0x81);

        $frame .= match (true) {
            $length < 126 => chr(0x80 | $length),
            $length < 65536 => chr(0x80 | 126).pack('n', $length),
            default => chr(0x80 | 127).pack('J', $length),
        };

        $masked = '';

        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        return $frame.$mask.$masked;
    }

    /**
     * @param  resource  $client
     */
    private static function readText(mixed $client): ?string
    {
        $header = fread($client, 2);

        if ($header === false || strlen($header) < 2) {
            return null;
        }

        $second = ord($header[1]);
        $masked = ($second & 0x80) === 0x80;
        $length = $second & 0x7F;

        if ($length === 126) {
            $extended = fread($client, 2);
            $length = $extended === false ? 0 : unpack('n', $extended)[1];
        } elseif ($length === 127) {
            $extended = fread($client, 8);
            $length = $extended === false ? 0 : unpack('J', $extended)[1];
        }

        $mask = $masked ? fread($client, 4) : '';
        $payload = $length > 0 ? fread($client, $length) : '';

        if ($payload === false) {
            return null;
        }

        if ($masked && is_string($mask) && strlen($mask) === 4) {
            $decoded = '';

            for ($i = 0; $i < strlen($payload); $i++) {
                $decoded .= $payload[$i] ^ $mask[$i % 4];
            }

            return $decoded;
        }

        return $payload;
    }

    private function isDefaultPort(int $port, bool $secure): bool
    {
        return $secure ? $port === 443 : $port === 80;
    }
}
