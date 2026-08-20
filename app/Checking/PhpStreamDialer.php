<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;
use App\Support\ProxyUrl;
use RuntimeException;
use Throwable;

final class PhpStreamDialer implements StreamDialer
{
    public function __construct(private ProxyTunnel $tunnel) {}

    public function connect(
        string $host,
        int $port,
        int $timeoutSeconds,
        IpFamily $family,
        ?string $proxyUrl = null,
        array $ssl = [],
    ): mixed {
        $proxy = ProxyUrl::tryParse($proxyUrl);

        if ($proxy === null) {
            return $this->direct($host, $port, $timeoutSeconds, $family, $ssl);
        }

        $stream = $this->open($proxy->host, $proxy->port, $timeoutSeconds, $family, $proxy->scheme === 'https');
        stream_set_timeout($stream, $timeoutSeconds);

        try {
            $this->tunnel->establish($stream, $proxy, $host, $port);

            if ($ssl !== []) {
                $this->enableTls($stream, $ssl);
            }
        } catch (Throwable $exception) {
            fclose($stream);

            throw $exception;
        }

        return $stream;
    }

    /**
     * @param  array<string, mixed>  $ssl
     * @return resource
     */
    private function direct(string $host, int $port, int $timeoutSeconds, IpFamily $family, array $ssl): mixed
    {
        $address = new SocketAddress($host, $port);
        $scheme = $ssl === [] ? 'tcp' : 'ssl';

        return $this->client(
            $address->remote($scheme),
            $timeoutSeconds,
            StreamSocket::context($family, $ssl === [] ? [] : ['ssl' => $ssl]),
            $scheme === 'ssl' ? 'TLS connection failed' : 'Connection failed',
        );
    }

    /**
     * @return resource
     */
    private function open(string $host, int $port, int $timeoutSeconds, IpFamily $family, bool $secure): mixed
    {
        $address = new SocketAddress($host, $port);
        $options = $secure ? [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
            ],
        ] : [];

        return $this->client(
            $address->remote($secure ? 'ssl' : 'tcp'),
            $timeoutSeconds,
            StreamSocket::context($family, $options),
            'Proxy connection failed',
        );
    }

    /**
     * @param  resource|null  $context
     * @return resource
     */
    private function client(string $remote, int $timeoutSeconds, mixed $context, string $fallback): mixed
    {
        $errorCode = 0;
        $errorMessage = '';

        try {
            $client = @stream_socket_client(
                $remote,
                $errorCode,
                $errorMessage,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                $context,
            );
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        if ($client === false) {
            throw new RuntimeException($errorMessage !== '' ? $errorMessage : $fallback);
        }

        return $client;
    }

    /**
     * @param  resource  $stream
     * @param  array<string, mixed>  $ssl
     */
    private function enableTls(mixed $stream, array $ssl): void
    {
        foreach ($ssl as $key => $value) {
            stream_context_set_option($stream, 'ssl', $key, $value);
        }

        $enabled = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        if ($enabled !== true) {
            throw new RuntimeException('TLS handshake through the proxy failed.');
        }
    }
}
