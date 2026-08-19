<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckResult>
 */
class CheckResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'probe_id' => Probe::factory(),
            'checked_at' => now(),
            'success' => true,
            'latency_ms' => 42,
            'http_status' => 200,
            'resolved_ip' => '1.1.1.1',
            'certificate_expires_at' => null,
            'message' => null,
            'condition_results' => [],
        ];
    }
}
