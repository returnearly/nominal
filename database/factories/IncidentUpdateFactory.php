<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentUpdate>
 */
class IncidentUpdateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'status' => IncidentStatus::Investigating,
            'message' => fake()->paragraph(),
            'posted_at' => now()->subMinutes(30),
        ];
    }
}
