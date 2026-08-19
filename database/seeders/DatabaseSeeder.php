<?php

namespace Database\Seeders;

use App\Enums\MonitorType;
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

        $probe = Probe::query()->firstOrCreate(
            ['slug' => 'local'],
            [
                'name' => 'Local',
                'queue' => 'checks.local',
                'enabled' => true,
            ],
        );

        $channel = NotificationChannel::query()->firstOrCreate(
            ['name' => 'Local mail'],
            [
                'type' => NotificationChannelType::Mail,
                'config' => ['to' => $user->email],
            ],
        );

        if (Monitor::query()->doesntExist()) {
            $monitor = Monitor::factory()->withDefaultConditions()->create([
                'name' => 'Example HTTP',
                'group' => 'core',
                'type' => MonitorType::Http,
                'target' => 'https://example.com',
            ]);
            $monitor->probes()->sync([$probe->id]);
            $monitor->notificationChannels()->sync([$channel->id]);
        }
    }
}
