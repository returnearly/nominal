<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DnsQueryType;
use App\Enums\HttpMethod;
use App\Enums\IpFamily;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Models\Probe;
use App\Support\EnumValue;
use App\Support\MonitorTags;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SaveMonitor implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?Monitor $monitor = null): Monitor
    {
        $monitor ??= new Monitor;
        $type = $this->type($input['type'] ?? $monitor->type);
        $intervalSeconds = (int) ($input['intervalSeconds'] ?? $input['interval_seconds'] ?? $monitor->interval_seconds ?? 60);

        $this->assertDomainExpirationInterval(
            $type,
            $intervalSeconds,
            $input['conditions'] ?? $monitor->conditions?->pluck('expression')->all(),
        );

        $monitor->fill([
            'name' => $input['name'] ?? $monitor->name,
            'description' => array_key_exists('description', $input)
                ? $this->description($input['description'])
                : $monitor->description,
            'tags' => array_key_exists('tags', $input)
                ? MonitorTags::normalize($input['tags'])
                : $monitor->tags,
            'type' => $type,
            'enabled' => $input['enabled'] ?? $monitor->enabled ?? true,
            'interval_seconds' => $intervalSeconds,
            'timeout_seconds' => $type->usesOutboundProbe()
                ? ($input['timeoutSeconds'] ?? $input['timeout_seconds'] ?? $monitor->timeout_seconds ?? 10)
                : ($monitor->timeout_seconds ?? 10),
            'ip_family' => $type->usesOutboundProbe()
                ? $this->ipFamily($input['ipFamily'] ?? $input['ip_family'] ?? $monitor->ip_family ?? IpFamily::Any)
                : IpFamily::Any,
            'target' => $input['target'] ?? $monitor->target,
            'method' => $type->usesHttpRequest()
                ? $this->method($input['method'] ?? $monitor->method ?? ($type->wrapsGraphQLBody() ? HttpMethod::Post : HttpMethod::Get))
                : null,
            'request_headers' => $type->usesRequestHeaders()
                ? ($input['requestHeaders'] ?? $input['request_headers'] ?? $monitor->request_headers ?? [])
                : null,
            'request_body' => $type->usesRequestBody()
                ? ($input['requestBody'] ?? $input['request_body'] ?? $monitor->request_body)
                : null,
            'dns_query_name' => $type->usesDnsQuery()
                ? ($input['dnsQueryName'] ?? $input['dns_query_name'] ?? $monitor->dns_query_name)
                : null,
            'dns_query_type' => $type->usesDnsQuery()
                ? $this->dnsQueryType($input['dnsQueryType'] ?? $input['dns_query_type'] ?? $monitor->dns_query_type ?? DnsQueryType::A)
                : null,
            'heartbeat_token' => $type === MonitorType::Heartbeat
                ? ($monitor->heartbeat_token ?: Str::random(48))
                : $monitor->heartbeat_token,
            'follow_redirects' => $type->usesHttpRequest()
                ? ($input['followRedirects'] ?? $input['follow_redirects'] ?? $monitor->follow_redirects ?? true)
                : true,
            'verify_tls' => $type->usesVerifyTls()
                ? ($input['verifyTls'] ?? $input['verify_tls'] ?? $monitor->verify_tls ?? true)
                : true,
            'proxy_url' => $type->usesProxy()
                ? $this->nullableString($input['proxyUrl'] ?? $input['proxy_url'] ?? $monitor->proxy_url)
                : null,
            'retention_days' => $input['retentionDays'] ?? $input['retention_days'] ?? $monitor->retention_days ?? 30,
            'status' => $monitor->status ?? MonitorStatus::Pending,
        ]);

        $monitor->save();

        if ($type === MonitorType::Heartbeat) {
            $monitor->conditions()->delete();
            $monitor->probes()->sync([]);
        } else {
            if (array_key_exists('conditions', $input) || $monitor->conditions()->doesntExist()) {
                $this->syncConditions($monitor, $input['conditions'] ?? null);
            }

            if (array_key_exists('probeIds', $input) || array_key_exists('probe_ids', $input) || $monitor->probes()->doesntExist()) {
                $this->syncProbes($monitor, $input['probeIds'] ?? $input['probe_ids'] ?? null);
            }
        }

        $monitor = $monitor->fresh(['conditions', 'probes', 'notificationChannels']) ?? $monitor;

        DispatchMonitorCheck::make()->handle($monitor, saved: true);

        return $monitor;
    }

    /**
     * @param  list<string>|null  $expressions
     */
    private function assertDomainExpirationInterval(MonitorType $type, int $intervalSeconds, ?array $expressions): void
    {
        if (! $type->supportsDomainExpiration()) {
            return;
        }

        if (! DetectDomainExpirationCondition::make()->handle($expressions)) {
            return;
        }

        if ($intervalSeconds < LookupDomainExpiration::MinimumIntervalSeconds) {
            throw ValidationException::withMessages([
                'intervalSeconds' => 'The minimum interval for a monitor with a [DOMAIN_EXPIRATION] condition is 300s (5m).',
            ]);
        }
    }

    /**
     * @param  list<string>|null  $expressions
     */
    private function syncConditions(Monitor $monitor, ?array $expressions): void
    {
        $expressions ??= DefaultConditionExpressions::make()->handle($monitor->type);

        $monitor->conditions()->delete();

        foreach (array_values($expressions) as $sort => $expression) {
            if (! is_string($expression) || trim($expression) === '') {
                continue;
            }

            $monitor->conditions()->create([
                'expression' => trim($expression),
                'sort' => $sort,
            ]);
        }
    }

    /**
     * @param  list<string>|null  $probeIds
     */
    private function syncProbes(Monitor $monitor, ?array $probeIds): void
    {
        if ($probeIds === null) {
            $probeIds = Probe::defaultIds();
        }

        $monitor->probes()->sync($probeIds);
    }

    private function type(mixed $type): MonitorType
    {
        if ($type instanceof MonitorType) {
            return $type;
        }

        return EnumValue::parse(MonitorType::class, $type);
    }

    private function ipFamily(mixed $family): IpFamily
    {
        return $family instanceof IpFamily ? $family : EnumValue::parse(IpFamily::class, $family);
    }

    private function method(mixed $method): HttpMethod
    {
        return $method instanceof HttpMethod ? $method : EnumValue::parse(HttpMethod::class, $method);
    }

    private function dnsQueryType(mixed $type): DnsQueryType
    {
        return $type instanceof DnsQueryType ? $type : EnumValue::parse(DnsQueryType::class, $type);
    }

    private function description(mixed $description): ?string
    {
        return $this->nullableString($description);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
