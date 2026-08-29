<?php

declare(strict_types=1);

namespace App\Support;

final class ChannelMailer
{
    /**
     * SMTP settings for a one-off mailer, or null to use the environment mailer.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    public static function smtpConfig(array $config): ?array
    {
        $host = self::string($config['host'] ?? null);

        if ($host === null) {
            return null;
        }

        $port = self::port($config['port'] ?? null);
        $encryption = self::string($config['encryption'] ?? null);

        $smtp = [
            'transport' => 'smtp',
            'host' => $host,
            'port' => $port,
            'username' => self::string($config['username'] ?? null),
            'password' => self::string($config['password'] ?? null),
            'scheme' => self::scheme($encryption, $port),
        ];

        if ($encryption === 'none') {
            $smtp['auto_tls'] = false;
        }

        return $smtp;
    }

    private static function scheme(?string $encryption, int $port): string
    {
        return match ($encryption) {
            'ssl', 'smtps' => 'smtps',
            'none', 'tls', 'starttls' => 'smtp',
            default => $port === 465 ? 'smtps' : 'smtp',
        };
    }

    private static function port(mixed $value): int
    {
        $port = self::string($value);

        return $port !== null ? (int) $port : 587;
    }

    private static function string(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
