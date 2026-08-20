<?php

declare(strict_types=1);

namespace App\Checking;

use App\Models\Monitor;

final class DomainHostname
{
    public static function fromMonitor(Monitor $monitor): ?string
    {
        return self::fromTarget($monitor->target);
    }

    public static function fromTarget(string $target): ?string
    {
        $target = trim($target);

        if ($target === '') {
            return null;
        }

        $target = preg_replace('#^(icmp|ping)://#i', '', $target) ?? $target;

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $target) === 1) {
            return self::normalize(parse_url($target, PHP_URL_HOST));
        }

        if (str_starts_with($target, '[')) {
            if (preg_match('/^\[([^\]]+)\]/', $target, $matches) === 1) {
                return self::normalize($matches[1]);
            }

            return null;
        }

        if (substr_count($target, ':') === 1) {
            [$host] = explode(':', $target, 2);

            return self::normalize($host);
        }

        return self::normalize($target);
    }

    private static function normalize(mixed $host): ?string
    {
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        if (! str_contains($host, '.')) {
            return null;
        }

        return $host;
    }
}
