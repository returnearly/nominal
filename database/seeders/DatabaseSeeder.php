<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotificationChannelType;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Probe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'admin@nominal.test'],
            [
                'name' => 'Nominal Admin',
                'password' => Hash::make('password'),
            ],
        );

        Probe::query()->firstOrCreate(
            ['slug' => 'local'],
            [
                'name' => 'Local',
                'queue' => 'checks.local',
                'enabled' => true,
                'is_default' => true,
            ],
        );

        $channel = NotificationChannel::query()->firstOrCreate(
            ['name' => 'Local mail'],
            [
                'type' => NotificationChannelType::Mail,
                'config' => ['to' => $user->email],
            ],
        );

        $this->call(DemoMonitorSeeder::class);

        $channel->monitors()->syncWithoutDetaching(
            Monitor::query()
                ->whereIn('group', ['demo', 'failing'])
                ->pluck('id'),
        );
    }
}
