<?php

declare(strict_types=1);

namespace App\Checking;

use RuntimeException;

final class RedisProtocol
{
    public static function encode(string ...$arguments): string
    {
        $payload = '*'.count($arguments)."\r\n";

        foreach ($arguments as $argument) {
            $payload .= '$'.strlen($argument)."\r\n{$argument}\r\n";
        }

        return $payload;
    }

    public static function decode(mixed $stream): mixed
    {
        $line = self::readLine($stream);

        if ($line === '') {
            throw new RuntimeException('Empty Redis response.');
        }

        $prefix = $line[0];
        $payload = substr($line, 1);

        return match ($prefix) {
            '+' => $payload,
            '-' => throw new RuntimeException($payload),
            ':' => (int) $payload,
            '$' => self::readBulk($stream, (int) $payload),
            '*' => self::readArray($stream, (int) $payload),
            default => throw new RuntimeException("Unexpected Redis response prefix [{$prefix}]."),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function parseInfo(string $info): array
    {
        $values = [];

        foreach (preg_split("/\r\n|\n|\r/", $info) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, ':');

            if ($separator === false) {
                continue;
            }

            $values[substr($line, 0, $separator)] = substr($line, $separator + 1);
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    public static function tokenize(string $command): array
    {
        $tokens = str_getcsv(trim($command), ' ', '"', '');

        return array_values(array_filter(
            $tokens,
            fn (mixed $token): bool => is_string($token) && $token !== '',
        ));
    }

    private static function readBulk(mixed $stream, int $length): ?string
    {
        if ($length < 0) {
            return null;
        }

        $body = self::readBytes($stream, $length);
        self::readBytes($stream, 2);

        return $body;
    }

    /**
     * @return list<mixed>
     */
    private static function readArray(mixed $stream, int $count): array
    {
        if ($count < 0) {
            return [];
        }

        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = self::decode($stream);
        }

        return $items;
    }

    private static function readLine(mixed $stream): string
    {
        $line = fgets($stream);

        if ($line === false) {
            throw new RuntimeException('Redis connection closed.');
        }

        return rtrim($line, "\r\n");
    }

    private static function readBytes(mixed $stream, int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($stream, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Redis connection closed.');
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }
}
