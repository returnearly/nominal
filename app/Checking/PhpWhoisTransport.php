<?php

declare(strict_types=1);

namespace App\Checking;

use RuntimeException;
use Throwable;

final class PhpWhoisTransport implements WhoisTransport
{
    public function query(string $server, string $query, int $timeoutSeconds): string
    {
        $address = str_contains($server, ':') ? $server : $server.':43';
        $errno = 0;
        $error = '';

        try {
            $stream = stream_socket_client(
                'tcp://'.$address,
                $errno,
                $error,
                $timeoutSeconds,
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "WHOIS query to {$address} failed: ".$exception->getMessage(),
                previous: $exception,
            );
        }

        if ($stream === false) {
            throw new RuntimeException("WHOIS query to {$address} failed: {$error}");
        }

        stream_set_timeout($stream, $timeoutSeconds);

        try {
            fwrite($stream, $query."\r\n");

            $output = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        if (! is_string($output) || $output === '') {
            throw new RuntimeException("WHOIS query to {$address} returned an empty response.");
        }

        return $output;
    }
}
