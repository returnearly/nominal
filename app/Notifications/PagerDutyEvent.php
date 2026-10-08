<?php

declare(strict_types=1);

namespace App\Notifications;

final class PagerDutyEvent
{
    public const string Endpoint = 'https://events.pagerduty.com/v2/enqueue';

    /**
     * @param  array<string, string|int|float|bool>  $details
     * @return array<string, mixed>
     */
    public static function make(
        string $action,
        string $dedupKey,
        string $summary,
        string $source,
        string $severity,
        ?string $component = null,
        ?string $group = null,
        ?string $class = null,
        array $details = [],
        ?string $clientUrl = null,
    ): array {
        $payload = [
            'summary' => self::limit($summary, 1024),
            'timestamp' => now()->toIso8601String(),
            'severity' => $severity,
            'source' => self::limit(self::blank($source, 'nominal'), 1024),
        ];

        foreach ([
            'component' => $component,
            'group' => $group,
            'class' => $class,
        ] as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $payload[$key] = self::limit($value, 255);
        }

        $customDetails = self::details($details);

        if ($customDetails !== []) {
            $payload['custom_details'] = $customDetails;
        }

        $event = [
            'event_action' => $action,
            'dedup_key' => $dedupKey,
            'payload' => $payload,
        ];

        if ($action === 'trigger' && is_string($clientUrl) && filter_var($clientUrl, FILTER_VALIDATE_URL)) {
            $event['client'] = 'Nominal';
            $event['client_url'] = $clientUrl;
            $event['links'] = [
                [
                    'href' => $clientUrl,
                    'text' => 'View in Nominal',
                ],
            ];
        }

        return $event;
    }

    public static function affectedSystem(string $target): string
    {
        $target = trim($target);

        if ($target === '') {
            return 'nominal';
        }

        $stripped = preg_replace('#^([a-z][a-z0-9+.-]*://)([^/@\s]+)@#i', '$1', $target);

        return self::limit(is_string($stripped) && $stripped !== '' ? $stripped : $target, 1024);
    }

    /**
     * @param  array<string, string|int|float|bool>  $details
     * @return array<string, string|int|float|bool>
     */
    private static function details(array $details): array
    {
        $clean = [];

        foreach ($details as $key => $value) {
            if (is_int($value) || is_float($value) || is_bool($value)) {
                $clean[$key] = $value;

                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $clean[$key] = self::limit($value, 1024);
        }

        return $clean;
    }

    private static function blank(string $value, string $fallback): string
    {
        $value = trim($value);

        return $value === '' ? $fallback : $value;
    }

    private static function limit(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return mb_strcut($value, 0, $max, 'UTF-8');
    }
}
