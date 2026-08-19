<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;
use Symfony\Component\Process\Process;
use Throwable;

final class IcmpThenTcpPingTransport implements PingTransport
{
    public function ping(string $host, int $timeoutSeconds, IpFamily $family): PingOutcome
    {
        $icmp = $this->icmp($host, $timeoutSeconds, $family);

        if ($icmp->connected) {
            return $icmp;
        }

        return $this->tcp($host, $timeoutSeconds, $family, $icmp->message);
    }

    private function icmp(string $host, int $timeoutSeconds, IpFamily $family): PingOutcome
    {
        $wait = PHP_OS_FAMILY === 'Darwin'
            ? (string) max(1, $timeoutSeconds * 1000)
            : (string) max(1, $timeoutSeconds);

        $command = ['ping', '-c', '1', '-W', $wait];

        if (PHP_OS_FAMILY !== 'Darwin') {
            $command = match ($family) {
                IpFamily::Ipv4 => ['ping', '-4', '-c', '1', '-W', $wait],
                IpFamily::Ipv6 => ['ping', '-6', '-c', '1', '-W', $wait],
                IpFamily::Any => ['ping', '-c', '1', '-W', $wait],
            };
        } elseif ($family === IpFamily::Ipv6) {
            $command = ['ping6', '-c', '1', '-W', $wait];
        }

        $command[] = $host;

        $started = hrtime(true);

        try {
            $process = new Process($command);
            $process->setTimeout($timeoutSeconds + 2);
            $process->run();
        } catch (Throwable $exception) {
            return new PingOutcome(false, null, null, $exception->getMessage());
        }

        $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);
        $output = $process->getOutput().$process->getErrorOutput();

        if (! $process->isSuccessful()) {
            return new PingOutcome(false, $latencyMs, $this->extractIp($output), trim($process->getErrorOutput()) ?: 'ICMP ping failed');
        }

        if (preg_match('/time[=<]\s*([\d.]+)\s*ms/i', $output, $matches) === 1) {
            $latencyMs = (int) round((float) $matches[1]);
        }

        return new PingOutcome(true, $latencyMs, $this->extractIp($output), null);
    }

    private function tcp(string $host, int $timeoutSeconds, IpFamily $family, ?string $icmpError): PingOutcome
    {
        $ports = [443, 80];
        $lastError = $icmpError ?? 'TCP fallback failed';

        foreach ($ports as $port) {
            $started = hrtime(true);
            $target = $family === IpFamily::Ipv6
                ? "tcp://[{$host}]:{$port}"
                : "tcp://{$host}:{$port}";

            $client = @stream_socket_client(
                $target,
                $errorCode,
                $errorMessage,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
            );

            $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);

            if ($client !== false) {
                $ip = $this->peerIp($client);
                fclose($client);

                return new PingOutcome(true, $latencyMs, $ip, null);
            }

            $lastError = $errorMessage !== '' ? $errorMessage : $lastError;
        }

        return new PingOutcome(false, null, null, $lastError);
    }

    private function extractIp(string $output): ?string
    {
        if (preg_match('/\((\d{1,3}(?:\.\d{1,3}){3}|[0-9a-f:]+)\)/i', $output, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/from\s+(\d{1,3}(?:\.\d{1,3}){3}|[0-9a-f:]+)/i', $output, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  resource  $client
     */
    private function peerIp(mixed $client): ?string
    {
        $name = stream_socket_get_name($client, true);

        if (! is_string($name) || $name === '') {
            return null;
        }

        if (str_starts_with($name, '[')) {
            $end = strpos($name, ']');

            return $end === false ? $name : substr($name, 1, $end - 1);
        }

        return explode(':', $name)[0];
    }
}
