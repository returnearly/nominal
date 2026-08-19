<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Conditions\ConditionExpression;
use App\Enums\HttpMethod;
use App\Enums\IpFamily;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'group' => 'core',
            'type' => MonitorType::Http,
            'enabled' => true,
            'interval_seconds' => 60,
            'timeout_seconds' => 10,
            'ip_family' => IpFamily::Any,
            'target' => 'https://example.com/health',
            'method' => HttpMethod::Get,
            'request_headers' => [],
            'request_body' => null,
            'follow_redirects' => true,
            'verify_tls' => true,
            'status' => MonitorStatus::Pending,
            'retention_days' => 30,
        ];
    }

    public function ping(): static
    {
        return $this->state(fn (): array => [
            'type' => MonitorType::Ping,
            'target' => 'example.com',
            'method' => null,
            'request_headers' => null,
            'request_body' => null,
        ]);
    }

    public function withDefaultConditions(): static
    {
        return $this->afterCreating(function (Monitor $monitor): void {
            foreach (ConditionExpression::defaultExpressions($monitor->type) as $sort => $expression) {
                $monitor->conditions()->create([
                    'expression' => $expression,
                    'sort' => $sort,
                ]);
            }
        });
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'enabled' => false,
        ]);
    }
}
