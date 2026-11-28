<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Probe;
use App\Models\User;
use Illuminate\Support\Str;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ProvisionInstance implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @return array{region: string, queue: string, admin_email: ?string, admin_created: bool}
     */
    public function handle(): array
    {
        $region = $this->region();
        $queue = 'checks.'.$region;

        Probe::query()->firstOrCreate(
            ['slug' => $region],
            [
                'name' => Str::headline($region),
                'queue' => $queue,
                'enabled' => true,
                'is_default' => true,
            ],
        );

        $email = $this->adminEmail();
        $password = config('nominal.admin.password');
        $password = is_string($password) ? $password : '';

        if ($email === '' || $password === '') {
            return [
                'region' => $region,
                'queue' => $queue,
                'admin_email' => null,
                'admin_created' => false,
            ];
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $this->adminName(),
                'password' => $password,
            ],
        );

        return [
            'region' => $region,
            'queue' => $queue,
            'admin_email' => $email,
            'admin_created' => $user->wasRecentlyCreated,
        ];
    }

    private function region(): string
    {
        $region = config('nominal.probe_region', 'local');
        $region = is_string($region) ? trim($region) : '';

        return $region !== '' ? $region : 'local';
    }

    private function adminEmail(): string
    {
        $email = config('nominal.admin.email');

        return is_string($email) ? trim($email) : '';
    }

    private function adminName(): string
    {
        $name = config('nominal.admin.name');
        $name = is_string($name) ? trim($name) : '';

        return $name !== '' ? $name : 'Nominal Admin';
    }
}
