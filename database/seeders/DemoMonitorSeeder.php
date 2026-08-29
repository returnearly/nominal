<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\DefaultConditionExpressions;
use App\Actions\DispatchMonitorCheck;
use App\Actions\SaveStatusPage;
use App\Enums\HttpMethod;
use App\Enums\IpFamily;
use App\Enums\MonitorType;
use App\Enums\StatusPageTheme;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\StatusPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoMonitorSeeder extends Seeder
{
    private const WindowShare = 0.3;

    public function run(): void
    {
        $probe = Probe::query()->firstOrCreate(
            ['slug' => 'local'],
            [
                'name' => 'Local',
                'queue' => 'checks.local',
                'enabled' => true,
                'is_default' => true,
            ],
        );

        foreach ($this->monitors() as $demo) {
            $monitor = Monitor::query()->firstOrCreate(
                ['name' => $demo['name']],
                $demo['attributes'],
            );

            if ($monitor->type !== MonitorType::Heartbeat) {
                if ($monitor->conditions()->doesntExist()) {
                    foreach ($demo['conditions'] as $sort => $expression) {
                        $monitor->conditions()->create([
                            'expression' => $expression,
                            'sort' => $sort,
                        ]);
                    }
                }

                $monitor->probes()->syncWithoutDetaching([$probe->id]);
            }

            if ($monitor->last_checked_at === null && $monitor->type !== MonitorType::Heartbeat) {
                DispatchMonitorCheck::make()->handle($monitor);
            }
        }

        $this->seedMaintenanceWindows();
        $this->seedStatusPage();
    }

    private function seedMaintenanceWindows(): void
    {
        $monitors = Monitor::query()->orderBy('name')->get();

        if ($monitors->isEmpty()) {
            return;
        }

        $share = max(1, (int) ceil($monitors->count() * self::WindowShare));
        $past = $monitors->take($share);
        $upcoming = $monitors->slice($share)->take($share);

        if ($upcoming->isEmpty()) {
            $upcoming = $past;
        }

        $this->seedWindow(
            title: 'Completed OS patching',
            message: 'Kernel and package updates on the example hosts. Checks ran throughout; no customer impact.',
            startsAt: now()->subDays(3),
            endsAt: now()->subDays(3)->addHours(2),
            monitors: $past,
        );

        $this->seedWindow(
            title: 'Database failover drill',
            message: 'Planned primary/replica failover. Brief connection errors are expected.',
            startsAt: now()->addDay(),
            endsAt: now()->addDay()->addHours(2),
            monitors: $upcoming,
        );
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     */
    private function seedWindow(
        string $title,
        string $message,
        Carbon $startsAt,
        Carbon $endsAt,
        Collection $monitors,
    ): void {
        $window = MaintenanceWindow::query()->firstOrCreate(
            ['title' => $title],
            [
                'message' => $message,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'applies_to_all' => false,
            ],
        );

        $window->monitors()->sync($monitors->modelKeys());
    }

    private function seedStatusPage(): void
    {
        $page = StatusPage::query()->firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Nominal Status',
                'headline' => 'Demo services',
                'description' => 'Public status for the monitors created by the demo seeder.',
                'theme' => StatusPageTheme::Dark,
                'published' => true,
                'show_targets' => false,
                'refresh_seconds' => 30,
            ],
        );

        SaveStatusPage::make()->handle([
            'published' => true,
            'monitorIds' => Monitor::query()->orderBy('name')->pluck('id')->all(),
        ], $page);
    }

    /**
     * @return list<array{name: string, conditions: list<string>, attributes: array<string, mixed>}>
     */
    private function monitors(): array
    {
        return [
            [
                'name' => 'Example HTTP',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::Http),
                'attributes' => $this->attributes(
                    type: MonitorType::Http,
                    target: 'https://example.com',
                    method: HttpMethod::Get,
                    description: 'Public example.com homepage. If this fails, example.com itself is down — nothing to page for.',
                ),
            ],
            [
                'name' => 'Example GraphQL',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::GraphQL),
                'attributes' => $this->attributes(
                    type: MonitorType::GraphQL,
                    target: 'https://countries.trevorblades.com/',
                    method: HttpMethod::Post,
                    requestBody: '{ __typename }',
                ),
            ],
            [
                'name' => 'Example Ping',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::Ping),
                'attributes' => $this->attributes(
                    type: MonitorType::Ping,
                    target: '1.1.1.1',
                ),
            ],
            [
                'name' => 'Example TCP',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::Tcp),
                'attributes' => $this->attributes(
                    type: MonitorType::Tcp,
                    target: 'tcp://1.1.1.1:443',
                ),
            ],
            [
                'name' => 'Example DNS',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::Dns),
                'attributes' => $this->attributes(
                    type: MonitorType::Dns,
                    target: '1.1.1.1',
                    dnsQueryName: 'example.com',
                    dnsQueryType: 'A',
                ),
            ],
            [
                'name' => 'Example TLS',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::Tls),
                'attributes' => $this->attributes(
                    type: MonitorType::Tls,
                    target: 'tls://1.1.1.1:443',
                ),
            ],
            [
                'name' => 'Example Heartbeat',
                'conditions' => [],
                'attributes' => $this->attributes(
                    type: MonitorType::Heartbeat,
                    target: 'backup-job',
                ),
            ],
            [
                'name' => 'Example UDP',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::Udp),
                'attributes' => $this->attributes(
                    type: MonitorType::Udp,
                    target: 'udp://1.1.1.1:53',
                ),
            ],
            [
                'name' => 'Example WebSocket',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::WebSocket),
                'attributes' => $this->attributes(
                    type: MonitorType::WebSocket,
                    target: 'wss://echo.websocket.events',
                ),
            ],
            [
                'name' => 'Example MySQL',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::Mysql),
                'attributes' => $this->attributes(
                    type: MonitorType::Mysql,
                    target: 'mysql://app:secret@db.example.com:3306/app',
                ),
            ],
            [
                'name' => 'Example Redis',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::Redis),
                'attributes' => $this->attributes(
                    type: MonitorType::Redis,
                    target: 'redis://:secret@cache.example.com:6379/0',
                ),
            ],
            [
                'name' => 'Example PostgreSQL',
                'conditions' => DefaultConditionExpressions::make()->handle(MonitorType::Postgres),
                'attributes' => $this->attributes(
                    type: MonitorType::Postgres,
                    target: 'postgres://app:secret@db.example.com:5432/app',
                ),
            ],
            [
                'name' => 'Failing HTTP status',
                'conditions' => ['[STATUS] == 500'],
                'attributes' => $this->attributes(
                    type: MonitorType::Http,
                    target: 'https://example.com',
                    method: HttpMethod::Get,
                    tags: ['synthetic'],
                ),
            ],
            [
                'name' => 'Failing GraphQL',
                'conditions' => ['[STATUS] == 500'],
                'attributes' => $this->attributes(
                    type: MonitorType::GraphQL,
                    target: 'https://countries.trevorblades.com/',
                    method: HttpMethod::Post,
                    tags: ['synthetic'],
                    requestBody: '{ __typename }',
                ),
            ],
            [
                'name' => 'Failing HTTP unreachable',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Http,
                    target: 'https://down.invalid',
                    method: HttpMethod::Get,
                    tags: ['synthetic'],
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing Ping',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Ping,
                    target: '192.0.2.1',
                    tags: ['synthetic'],
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing TCP',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Tcp,
                    target: '192.0.2.1:59999',
                    tags: ['synthetic'],
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
                    tags: ['synthetic'],
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing TLS',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Tls,
                    target: '192.0.2.1:59999',
                    tags: ['synthetic'],
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing UDP',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Udp,
                    target: 'not-a-real-host.invalid:9',
                    tags: ['synthetic'],
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing WebSocket',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::WebSocket,
                    target: 'ws://down.invalid/socket',
                    tags: ['synthetic'],
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing MySQL',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Mysql,
                    target: 'mysql://app:secret@192.0.2.1:3306/app',
                    tags: ['synthetic'],
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing Redis',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Redis,
                    target: 'redis://192.0.2.1:6379/0',
                    tags: ['synthetic'],
                    timeoutSeconds: 5,
                ),
            ],
            [
                'name' => 'Failing PostgreSQL',
                'conditions' => ['[CONNECTED] == true'],
                'attributes' => $this->attributes(
                    type: MonitorType::Postgres,
                    target: 'postgres://app:secret@192.0.2.1:5432/app',
                    tags: ['synthetic'],
                    timeoutSeconds: 5,
                ),
            ],
        ];
    }

    /**
     * @param  list<string>  $tags
     * @return array<string, mixed>
     */
    private function attributes(
        MonitorType $type,
        string $target,
        ?HttpMethod $method = null,
        int $timeoutSeconds = 10,
        ?string $dnsQueryName = null,
        ?string $dnsQueryType = null,
        array $tags = ['example'],
        ?string $description = null,
        ?string $requestBody = null,
    ): array {
        return [
            'description' => $description ?? (in_array('synthetic', $tags, true)
                ? 'Intentionally broken so local installs have something red. Safe to ignore or delete.'
                : null),
            'tags' => $tags,
            'type' => $type,
            'target' => $target,
            'method' => $method,
            'request_body' => $requestBody,
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
