<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Probe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Probe>
 */
class ProbeFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => fake()->city().' probe',
            'queue' => 'checks.'.$slug,
            'enabled' => true,
            'is_default' => false,
        ];
    }

    public function asDefault(): static
    {
        return $this->state(fn (): array => [
            'is_default' => true,
        ]);
    }
}
