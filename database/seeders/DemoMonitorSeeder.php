<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\DispatchMonitorCheck;
use App\Conditions\ConditionExpression;
use App\Enums\HttpMethod;
use App\Enums\IpFamily;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Database\Seeder;

class DemoMonitorSeeder extends Seeder
{
    public function run(): void
    {
        $probe = Probe::query()->firstOrCreate(
            ['slug' => 'local'],
            [
                'name' => 'Local',
                'queue' => 'checks.local',
                'enabled' => true,
            ],
        );

        foreach ($this->monitors() as $demo) {
            $monitor = Monitor::query()->firstOrCreate(
                ['name' => $demo['name']],
                $demo['attributes'],
            );

            if ($monitor->conditions()->doesntExist()) {
                foreach ($demo['conditions'] as $sort => $expression) {
                    $monitor->conditions()->create([
                        'expression' => $expression,
                        'sort' => $sort,
                    ]);
                }
            }

            $monitor->probes()->syncWithoutDetaching([$probe->id]);

            if ($monitor->last_checked_at === null && $monitor->type !== MonitorType::Heartbeat) {
                DispatchMonitorCheck::make()->handle($monitor);
            }
        }
    }

    /**
     * @return list<array{name: string, conditions: list<string>, attributes: array<string, mixed>}>
     */
    private function monitors(): array
    {
        return [
            [
                'name' => 'Example HTTP',
                'conditions' => ConditionExpression::defaultExpressions(MonitorType::Http),
                'attributes' => $this->attributes(
                    type: MonitorType::Http,
                    target: 'https://example.com',
                    method: HttpMethod::Get,
                ),
            ],
            [
                'name' => 'Example Ping',
                'conditions' => ConditionExpression::defaultExpressions(MonitorType::Ping),
                'attributes' => $this->attributes(
                    type: MonitorType::Ping,
                    target: '1.1.1.1',
                ),
            ],
            [
                'name' => 'Example TCP',
                'conditions' => ConditionExpression::defaultExpressions(MonitorType::Tcp),
                'attributes' => $this->attributes(
                    type: MonitorType::Tcp,
                    target: 'tcp://1.1.1.1:443',
                ),
            ],
            [
                'name' => 'Example DNS',
                'conditions' => ConditionExpression::defaultExpressions(MonitorType::Dns),
                'attributes' => $this->attributes(
                    type: MonitorType::Dns,
                    target: '1.1.1.1',
                    dnsQueryName: 'example.com',
                    dnsQueryType: 'A',
                ),
            ],
            [
                'name' => 'Example TLS',
                'conditions' => ConditionExpression::defaultExpressions(MonitorType::Tls),
                'attributes' => $this->attributes(
                    type: MonitorType::Tls,
                    target: 'tls://1.1.1.1:443',
                ),
            ],
            [
                'name' => 'Example Heartbeat',
                'conditions' => ConditionExpression::defaultExpressions(MonitorType::Heartbeat),
                'attributes' => $this->attributes(
                    type: MonitorType::Heartbeat,
                    target: 'backup-job',
                ),
            ],
            [
                'name' => 'Example UDP',
                'conditions' => ConditionExpression::defaultExpressions(MonitorType::Udp),
                'attributes' => $this->attributes(
                    type: MonitorType::Udp,
                    target: 'udp://1.1.1.1:53',
                ),
            ],
            [
                'name' => 'Example WebSocket',
                'conditions' => ConditionExpression::defaultExpressions(MonitorType::WebSocket),
                'attributes' => $this->attributes(
                    type: MonitorType::WebSocket,
                    target: 'wss://echo.websocket.events',
                ),
            ],
            [
                'name' => 'Failing HTTP status',
                'conditions' => ['[STATUS] == 500'],
                'attributes' => $this->attributes(
                    type: MonitorType::Http,
                    target: 'https://example.com',
                    method: HttpMethod::Get,
                    group: 'failing',
                ),
            ],
            [
                'name' => 'Failing HTTP unreachable',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Http,
                    target: 'https://down.invalid',
                    method: HttpMethod::Get,
                    group: 'failing',
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing Ping',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Ping,
                    target: '192.0.2.1',
                    group: 'failing',
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing TCP',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Tcp,
                    target: '192.0.2.1:59999',
                    group: 'failing',
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing DNS',
                'conditions' => ['[DNS_RCODE] == NOERROR'],
                'attributes' => $this->attributes(
                    type: MonitorType::Dns,
                    target: '1.1.1.1',
                    dnsQueryName: 'this-name-should-not-exist.invalid',
                    dnsQueryType: 'A',
                    group: 'failing',
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing TLS',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Tls,
                    target: '192.0.2.1:59999',
                    group: 'failing',
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing UDP',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Udp,
                    target: 'not-a-real-host.invalid:9',
                    group: 'failing',
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing WebSocket',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::WebSocket,
                    target: 'ws://down.invalid/socket',
                    group: 'failing',
                    timeoutSeconds: 5,
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(
        MonitorType $type,
        string $target,
        ?HttpMethod $method = null,
        string $group = 'demo',
        int $timeoutSeconds = 10,
        ?string $dnsQueryName = null,
        ?string $dnsQueryType = null,
    ): array {
        return [
            'group' => $group,
            'type' => $type,
            'target' => $target,
            'method' => $method,
            'enabled' => true,
            'interval_seconds' => 60,
            'timeout_seconds' => $timeoutSeconds,
            'ip_family' => IpFamily::Any,
            'follow_redirects' => true,
            'verify_tls' => true,
            'retention_days' => 30,
            'dns_query_name' => $dnsQueryName,
            'dns_query_type' => $dnsQueryType,
        ];
    }
}
