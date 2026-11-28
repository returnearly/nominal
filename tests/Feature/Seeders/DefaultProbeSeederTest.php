<?php

declare(strict_types=1);

use App\Models\Probe;
use Database\Seeders\DefaultProbeSeeder;

it('creates the default local probe once', function () {
    $this->seed(DefaultProbeSeeder::class);
    $this->seed(DefaultProbeSeeder::class);

    $probe = Probe::query()->where('slug', 'local')->sole();

    expect(Probe::query()->count())->toBe(1)
        ->and($probe->name)->toBe('Local')
        ->and($probe->queue)->toBe('checks.local')
        ->and($probe->enabled)->toBeTrue()
        ->and($probe->is_default)->toBeTrue();
});
