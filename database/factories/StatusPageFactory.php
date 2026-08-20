<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusPageTheme;
use App\Models\StatusPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StatusPage>
 */
class StatusPageFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' status';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'custom_domain' => null,
            'headline' => 'Service status',
            'description' => 'Current status for customer-facing services.',
            'logo_url' => null,
            'favicon_url' => null,
            'footer_text' => null,
            'custom_css' => null,
            'theme' => StatusPageTheme::Dark,
            'published' => true,
            'show_targets' => false,
            'password' => null,
            'refresh_seconds' => 30,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => [
            'published' => false,
        ]);
    }

    public function passwordProtected(string $password = 'secret'): static
    {
        return $this->state(fn (): array => [
            'password' => $password,
        ]);
    }

    public function onDomain(string $domain = 'status.example.test'): static
    {
        return $this->state(fn (): array => [
            'custom_domain' => $domain,
        ]);
    }
}
