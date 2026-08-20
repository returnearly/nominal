<?php

declare(strict_types=1);

namespace App\Checking;

use App\Support\ProxyUrl;
use RuntimeException;

final class ProxyTunnel
{
    /**
     * @param  resource  $stream
     */
    public function establish(mixed $stream, ProxyUrl $proxy, string $host, int $port): void
    {
        if ($proxy->isHttp()) {
            $this->httpConnect($stream, $proxy, $host, $port);

            return;
        }

        match ($proxy->scheme) {
            'socks4', 'socks4a' => $this->socks4($stream, $proxy, $host, $port),
            default => $this->socks5($stream, $proxy, $host, $port),
        };
    }

    /**
     * @param  resource  $stream
     */
    private function httpConnect(mixed $stream, ProxyUrl $proxy, string $host, int $port): void
    {
        $authority = $this->authority($host, $port);
        $lines = [
            "CONNECT {$authority} HTTP/1.1",
            "Host: {$authority}",
        ];

        $authorization = $proxy->basicAuthorization();

        if ($authorization !== null) {
            $lines[] = 'Proxy-Authorization: '.$authorization;
        }

        $lines[] = 'Proxy-Connection: Keep-Alive';

        fwrite($stream, implode("\r\n", $lines)."\r\n\r\n");
        $raw = $this->readHeaders($stream);
        $statusLine = strstr($raw, "\r\n", true) ?: $raw;

        if (preg_match('/^HTTP\/\d(?:\.\d)? 200\b/i', $statusLine) !== 1) {
            throw new RuntimeException('HTTP proxy CONNECT failed: '.trim($statusLine));
        }
    }

    /**
     * @param  resource  $stream
     */
    private function socks5(mixed $stream, ProxyUrl $proxy, string $host, int $port): void
    {
        $methods = $proxy->username !== null ? "\x05\x02\x00\x02" : "\x05\x01\x00";
        fwrite($stream, $methods);

        $choice = $this->readExact($stream, 2);

        if (ord($choice[0]) !== 0x05) {
            throw new RuntimeException('SOCKS5 proxy returned an invalid version.');
        }

        $method = ord($choice[1]);

        if ($method === 0xFF) {
            throw new RuntimeException('SOCKS5 proxy rejected authentication methods.');
        }

        if ($method === 0x02) {
            $this->socks5UsernamePassword($stream, $proxy);
        } elseif ($method !== 0x00) {
            throw new RuntimeException('SOCKS5 proxy selected an unsupported authentication method.');
        }

        fwrite($stream, "\x05\x01\x00".$this->socksAddress($proxy, $host).pack('n', $port));

        $reply = $this->readExact($stream, 4);

        if (ord($reply[0]) !== 0x05 || ord($reply[1]) !== 0x00) {
            throw new RuntimeException('SOCKS5 proxy CONNECT failed with code '.ord($reply[1]).'.');
        }

        $this->discardSocksBind($stream, ord($reply[3]));
    }

    /**
     * @param  resource  $stream
     */
    private function socks5UsernamePassword(mixed $stream, ProxyUrl $proxy): void
    {
        $username = $proxy->username ?? '';
        $password = $proxy->password ?? '';

        if (strlen($username) > 255 || strlen($password) > 255) {
            throw new RuntimeException('SOCKS5 username or password is too long.');
        }

        fwrite($stream, "\x01".chr(strlen($username)).$username.chr(strlen($password)).$password);
        $status = $this->readExact($stream, 2);

        if (ord($status[1]) !== 0x00) {
            throw new RuntimeException('SOCKS5 proxy authentication failed.');
        }
    }

    /**
     * @param  resource  $stream
     */
    private function socks4(mixed $stream, ProxyUrl $proxy, string $host, int $port): void
    {
        $user = $proxy->username ?? '';
        $ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

        if (is_string($ip) && $proxy->scheme !== 'socks4a') {
            fwrite($stream, pack('CCn', 4, 1, $port).inet_pton($ip).$user."\0");
        } else {
            fwrite($stream, pack('CCn', 4, 1, $port)."\x00\x00\x00\x01".$user."\0".$host."\0");
        }

        $reply = $this->readExact($stream, 8);

        if (ord($reply[1]) !== 0x5A) {
            throw new RuntimeException('SOCKS4 proxy CONNECT failed.');
        }
    }

    /**
     * @param  resource  $stream
     */
    private function discardSocksBind(mixed $stream, int $atyp): void
    {
        $rest = match ($atyp) {
            0x01 => 6,
            0x04 => 18,
            0x03 => 1 + ord($this->readExact($stream, 1)) + 2,
            default => throw new RuntimeException('SOCKS5 proxy returned an invalid address type.'),
        };

        if ($atyp !== 0x03) {
            $this->readExact($stream, $rest);

            return;
        }

        $this->readExact($stream, $rest - 1);
    }

    private function socksAddress(ProxyUrl $proxy, string $host): string
    {
        if (! $proxy->remoteDns()) {
            $ip = filter_var($host, FILTER_VALIDATE_IP);

            if (is_string($ip)) {
                $packed = inet_pton($ip);

                if ($packed !== false) {
                    $atyp = str_contains($ip, ':') ? "\x04" : "\x01";

                    return $atyp.$packed;
                }
            } else {
                $resolved = gethostbyname($host);

                if ($resolved !== $host) {
                    $packed = inet_pton($resolved);

                    if ($packed !== false) {
                        return "\x01".$packed;
                    }
                }
            }
        }

        if (strlen($host) > 255) {
            throw new RuntimeException('SOCKS destination hostname is too long.');
        }

        return "\x03".chr(strlen($host)).$host;
    }

    private function authority(string $host, int $port): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return "[{$host}]:{$port}";
        }

        return "{$host}:{$port}";
    }

    /**
     * @param  resource  $stream
     */
    private function readHeaders(mixed $stream): string
    {
        $raw = '';

        while (! feof($stream) && ! str_contains($raw, "\r\n\r\n")) {
            $chunk = fread($stream, 1024);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $raw .= $chunk;
        }

        if (! str_contains($raw, "\r\n\r\n") && $raw === '') {
            throw new RuntimeException('HTTP proxy closed the connection during CONNECT.');
        }

        return $raw;
    }

    /**
     * @param  resource  $stream
     */
    private function readExact(mixed $stream, int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($stream, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Proxy closed the connection during the handshake.');
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }
}
