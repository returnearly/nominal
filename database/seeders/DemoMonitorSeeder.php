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

        foreach (MonitorType::cases() as $type) {
            $demo = $this->monitor($type);

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

            if ($monitor->last_checked_at === null) {
                DispatchMonitorCheck::make()->handle($monitor);
            }
        }
    }

    /**
     * @return array{name: string, conditions: list<string>, attributes: array<string, mixed>}
     */
    private function monitor(MonitorType $type): array
    {
        $specific = match ($type) {
            MonitorType::Http => [
                'name' => 'Example HTTP',
                'target' => 'https://example.com',
                'method' => HttpMethod::Get,
            ],
            MonitorType::Ping => [
                'name' => 'Example Ping',
                'target' => '1.1.1.1',
                'method' => null,
            ],
        };

        return [
            'name' => $specific['name'],
            'conditions' => ConditionExpression::defaultExpressions($type),
            'attributes' => [
                'group' => 'demo',
                'type' => $type,
                'target' => $specific['target'],
                'method' => $specific['method'],
                'enabled' => true,
                'interval_seconds' => 60,
                'timeout_seconds' => 10,
                'ip_family' => IpFamily::Any,
                'follow_redirects' => true,
                'verify_tls' => true,
                'retention_days' => 30,
            ],
        ];
    }
}
