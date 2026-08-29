<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends Factory<MaintenanceWindow>
 */
class MaintenanceWindowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Database upgrade',
            'message' => 'Expected downtime during the upgrade.',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'applies_to_all' => false,
            'cancelled_at' => null,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'cancelled_at' => now(),
        ]);
    }

    public function forAll(): static
    {
        return $this->state(fn (): array => [
            'applies_to_all' => true,
        ]);
    }

    public function indefinite(): static
    {
        return $this->state(fn (): array => [
            'ends_at' => null,
        ]);
    }

    /**
     * @param  iterable<Monitor|string>  $monitors
     */
    public function withMonitors(iterable $monitors): static
    {
        return $this->afterCreating(function (MaintenanceWindow $window) use ($monitors): void {
            $ids = Collection::make($monitors)
                ->map(fn (Monitor|string $monitor): string => $monitor instanceof Monitor ? $monitor->id : $monitor)
                ->all();

            $window->monitors()->sync($ids);
        });
    }
}
