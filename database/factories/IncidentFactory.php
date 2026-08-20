<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IncidentImpact;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\StatusPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status_page_id' => StatusPage::factory(),
            'title' => fake()->sentence(4),
            'status' => IncidentStatus::Investigating,
            'impact' => IncidentImpact::Minor,
            'started_at' => now()->subHour(),
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now()->subMinutes(10),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => IncidentStatus::Scheduled,
            'impact' => IncidentImpact::None,
            'started_at' => now()->addDay(),
        ]);
    }
}
