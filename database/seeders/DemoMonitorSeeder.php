<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\DispatchMonitorCheck;
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
                $monitor->conditions()->create([
                    'expression' => $demo['condition'],
                    'sort' => 0,
                ]);
            }

            $monitor->probes()->syncWithoutDetaching([$probe->id]);

            if ($monitor->last_checked_at === null) {
                DispatchMonitorCheck::make()->handle($monitor);
            }
        }
    }

    /**
     * @return list<array{name: string, condition: string, attributes: array<string, mixed>}>
     */
    private function monitors(): array
    {
        return [
            [
                'name' => 'Example HTTP',
                'condition' => '[STATUS] == 200',
                'attributes' => $this->attributes(
                    type: MonitorType::Http,
                    target: 'https://example.com',
                    method: HttpMethod::Get,
                ),
            ],
            [
                'name' => 'Example Ping',
                'condition' => '[CONNECTED] == true',
                'attributes' => $this->attributes(
                    type: MonitorType::Ping,
                    target: '1.1.1.1',
                ),
            ],
            [
                'name' => 'Failing HTTP status',
                'condition' => '[STATUS] == 500',
                'attributes' => $this->attributes(
                    type: MonitorType::Http,
                    target: 'https://example.com',
                    method: HttpMethod::Get,
                    group: 'failing',
                ),
            ],
            [
                'name' => 'Failing HTTP unreachable',
                'condition' => '[CONNECTED] == true',
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
                'condition' => '[CONNECTED] == true',
                'attributes' => $this->attributes(
                    type: MonitorType::Ping,
                    target: '192.0.2.1',
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
        ];
    }
}
