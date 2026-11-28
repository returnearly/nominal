<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Probe;
use Illuminate\Database\Seeder;

class DefaultProbeSeeder extends Seeder
{
    public function run(): void
    {
        Probe::query()->firstOrCreate(
            ['slug' => 'local'],
            [
                'name' => 'Local',
                'queue' => 'checks.local',
                'enabled' => true,
                'is_default' => true,
            ],
        );
    }
}
