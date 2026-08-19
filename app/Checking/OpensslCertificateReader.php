<?php

declare(strict_types=1);

namespace App\Checking;

use DateTimeImmutable;
use Throwable;

final class OpensslCertificateReader implements TlsCertificateReader
{
    public function expiresAt(string $host, int $port, int $timeoutSeconds): ?DateTimeImmutable
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        try {
            $client = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errorCode,
                $errorMessage,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                $context,
            );
        } catch (Throwable) {
            return null;
        }

        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

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
