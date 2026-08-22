<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors;

use App\Conditions\ConditionExpression;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Support\Str;

final class MonitorFormState
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Monitor $monitor): array
    {
        $monitor->loadMissing(['conditions', 'probes', 'notificationChannels']);

        return [
            'name' => self::copyName($monitor->name),
            'description' => $monitor->description,
            'tags' => $monitor->tags,
            'type' => $monitor->type,
            'target' => $monitor->target,
            'method' => $monitor->method,
            'ip_family' => $monitor->ip_family,
            'interval_seconds' => $monitor->interval_seconds,
            'timeout_seconds' => $monitor->timeout_seconds,
            'retention_days' => $monitor->retention_days,
            'enabled' => $monitor->enabled,
            'follow_redirects' => $monitor->follow_redirects,
            'verify_tls' => $monitor->verify_tls,
            'proxy_url' => $monitor->proxy_url,
            'request_headers' => $monitor->requestHeadersArray(),
            'request_body' => $monitor->request_body,
            'dns_query_name' => $monitor->dns_query_name,
            'dns_query_type' => $monitor->dns_query_type,
            'conditions' => self::conditions($monitor),
            'probes' => self::probes($monitor),
            'notificationChannels' => $monitor->notificationChannels->modelKeys(),
        ];
    }

    public static function copyName(string $name): string
    {
        $suffix = ' (copy)';
        $limit = 255 - mb_strlen($suffix);

        if (mb_strlen($name) <= $limit) {
            return $name.$suffix;
        }

        return rtrim(mb_substr($name, 0, $limit)).$suffix;
    }

    /**
     * @return array<string, array{placeholder: string, path: string, comparator: string, value: string}>
     */
    private static function conditions(Monitor $monitor): array
    {
        if (! $monitor->type->usesOutboundProbe()) {
            return [];
        }

        $items = [];

        foreach ($monitor->conditions as $condition) {
            $items[(string) Str::uuid()] = ConditionExpression::parse($condition->expression);
        }

        if ($items === []) {
            return ConditionExpression::defaultFormState($monitor->type);
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private static function probes(Monitor $monitor): array
    {
        if (! $monitor->type->usesOutboundProbe()) {
            return [];
        }

        $ids = $monitor->probes->modelKeys();

        return $ids === [] ? Probe::defaultIds() : $ids;
    }
}
