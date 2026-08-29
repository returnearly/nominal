<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\DnsQueryType;
use InvalidArgumentException;
use RuntimeException;

final class DnsMessage
{
    /**
     * @return array<int, string>
     */
    public const RCODES = [
        0 => 'NOERROR',
        1 => 'FORMERR',
        2 => 'SERVFAIL',
        3 => 'NXDOMAIN',
        4 => 'NOTIMP',
        5 => 'REFUSED',
        6 => 'YXDOMAIN',
        7 => 'YXRRSET',
        8 => 'NXRRSET',
        9 => 'NOTAUTH',
        10 => 'NOTZONE',
    ];

    public static function query(string $name, DnsQueryType $type, int $id): string
    {
        $header = pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0);

        return $header.self::encodeName(self::queryName($name, $type)).pack('nn', $type->wireType(), 1);
    }

    /**
     * @return array{rcode: string, answers: list<string>}
     */
    public static function parse(string $packet): array
    {
        if (strlen($packet) < 12) {
            throw new RuntimeException('DNS response is too short.');
        }

        $header = unpack('nid/nflags/nqdcount/nancount/nnscount/narcount', substr($packet, 0, 12));

        if (! is_array($header)) {
            throw new RuntimeException('Invalid DNS header.');
        }

        $offset = 12;
        $rcode = self::RCODES[$header['flags'] & 0x000F] ?? (string) ($header['flags'] & 0x000F);

        for ($i = 0; $i < $header['qdcount']; $i++) {
            self::readName($packet, $offset);
            $offset += 4;
        }

        $answers = [];

        for ($i = 0; $i < $header['ancount']; $i++) {
            self::readName($packet, $offset);

            if (strlen($packet) < $offset + 10) {
                break;
            }

            $record = unpack('ntype/nclass/Nttl/nlength', substr($packet, $offset, 10));
            $offset += 10;

            if (! is_array($record) || strlen($packet) < $offset + $record['length']) {
                break;
            }

            $rdata = substr($packet, $offset, $record['length']);
            $offset += $record['length'];
            $decoded = self::decodeRdata($packet, $rdata, $record['type'], $offset - $record['length']);

            if ($decoded !== null) {
                $answers[] = $decoded;
            }
        }

        return [
            'rcode' => $rcode,
            'answers' => $answers,
        ];
    }

    public static function queryName(string $name, DnsQueryType $type): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('DNS query name cannot be empty.');
        }

        if ($type !== DnsQueryType::PTR) {
            return rtrim($name, '.');
        }

        $ip = filter_var($name, FILTER_VALIDATE_IP);

        if ($ip === false) {
            return rtrim($name, '.');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_reverse(explode('.', $ip))).'.in-addr.arpa';
        }

        $hex = unpack('H*', inet_pton($ip));
        $nibbles = str_split($hex[1] ?? '');

        return implode('.', array_reverse($nibbles)).'.ip6.arpa';
    }

    private static function encodeName(string $name): string
    {
        $encoded = '';

        foreach ($name === '' ? [] : explode('.', rtrim($name, '.')) as $label) {
            $encoded .= chr(strlen($label)).$label;
        }

        return $encoded."\0";
    }

    private static function readName(string $packet, int &$offset, int $depth = 0): string
    {
        if ($depth > 10 || $offset >= strlen($packet)) {
            throw new RuntimeException('Invalid DNS name.');
        }

        $labels = [];

        while ($offset < strlen($packet)) {
            $length = ord($packet[$offset]);

            if ($length === 0) {
                $offset++;
                break;
            }

            if (($length & 0xC0) === 0xC0) {
                if ($offset + 1 >= strlen($packet)) {
                    throw new RuntimeException('Invalid DNS compression pointer.');
                }

                $pointer = (($length & 0x3F) << 8) | ord($packet[$offset + 1]);
                $offset += 2;
                $jump = $pointer;
                $labels[] = self::readName($packet, $jump, $depth + 1);

                return implode('.', array_filter($labels, fn (string $label): bool => $label !== ''));
            }

            $offset++;
            $labels[] = substr($packet, $offset, $length);
            $offset += $length;
        }

        return implode('.', $labels);
    }

    private static function decodeRdata(string $packet, string $rdata, int $type, int $rdataOffset): ?string
    {
        return match ($type) {
            1 => strlen($rdata) === 4 ? inet_ntop($rdata) ?: null : null,
            28 => strlen($rdata) === 16 ? inet_ntop($rdata) ?: null : null,
            2, 5, 12 => self::readName($packet, $rdataOffset),
            15 => self::decodeMx($packet, $rdata, $rdataOffset),
            16 => self::decodeTxt($rdata),
            33 => self::decodeSrv($packet, $rdata, $rdataOffset),
            default => null,
        };
    }

    private static function decodeMx(string $packet, string $rdata, int $rdataOffset): ?string
    {
        if (strlen($rdata) < 3) {
            return null;
        }

        $preference = unpack('n', substr($rdata, 0, 2));
        $offset = $rdataOffset + 2;
        $exchange = self::readName($packet, $offset);

        return ($preference[1] ?? 0).' '.$exchange;
    }

    private static function decodeTxt(string $rdata): string
    {
        $chunks = [];
        $offset = 0;

        while ($offset < strlen($rdata)) {
            $length = ord($rdata[$offset]);
            $offset++;
            $chunks[] = substr($rdata, $offset, $length);
            $offset += $length;
        }

        return implode('', $chunks);
    }

    private static function decodeSrv(string $packet, string $rdata, int $rdataOffset): ?string
    {
        if (strlen($rdata) < 7) {
            return null;
        }

        $fields = unpack('npriority/nweight/nport', substr($rdata, 0, 6));
        $offset = $rdataOffset + 6;
        $target = self::readName($packet, $offset);

        return $target.':'.($fields['port'] ?? 0);
    }
}
