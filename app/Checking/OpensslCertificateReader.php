<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;
use DateTimeImmutable;
use Throwable;

final class OpensslCertificateReader implements TlsCertificateReader
{
    public function __construct(private StreamDialer $dialer) {}

    public function expiresAt(string $host, int $port, int $timeoutSeconds, ?string $proxyUrl = null): ?DateTimeImmutable
    {
        try {
            $client = $this->dialer->connect($host, $port, $timeoutSeconds, IpFamily::Any, $proxyUrl, [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'peer_name' => $host,
            ]);
        } catch (Throwable) {
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
