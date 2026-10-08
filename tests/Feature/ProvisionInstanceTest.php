<?php

declare(strict_types=1);

use App\Actions\ProvisionInstance;
use App\Models\Probe;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the default probe and the first admin user', function () {
    config([
        'nominal.probe_region' => 'local',
        'nominal.admin.email' => 'admin@nominal.test',
        'nominal.admin.name' => 'Nominal Admin',
        'nominal.admin.password' => 'generated-secret',
    ]);

    $result = ProvisionInstance::make()->handle();

    expect($result)->toMatchArray([
        'region' => 'local',
        'queue' => 'checks.local',
        'admin_email' => 'admin@nominal.test',
        'admin_created' => true,
    ]);

    $probe = Probe::query()->where('slug', 'local')->first();
    $user = User::query()->where('email', 'admin@nominal.test')->first();

    expect($probe)->not->toBeNull()
        ->and($probe->queue)->toBe('checks.local')
        ->and($probe->is_default)->toBeTrue()
        ->and($probe->enabled)->toBeTrue()
        ->and($user)->not->toBeNull()
        ->and($user->name)->toBe('Nominal Admin')
        ->and(Hash::check('generated-secret', $user->password))->toBeTrue();
});

it('leaves an existing admin password in place', function () {
    config([
        'nominal.probe_region' => 'local',
        'nominal.admin.email' => 'admin@nominal.test',
        'nominal.admin.password' => 'generated-secret',
    ]);

    $user = User::factory()->create([
        'email' => 'admin@nominal.test',
        'password' => 'already-set',
    ]);

    $result = ProvisionInstance::make()->handle();

    expect($result['admin_created'])->toBeFalse()
        ->and(Hash::check('already-set', $user->fresh()->password))->toBeTrue();
});

it('creates the probe when admin credentials are absent', function () {
    config([
        'nominal.probe_region' => 'us-east',
        'nominal.admin.email' => ' ',
        'nominal.admin.password' => '',
    ]);

    $result = ProvisionInstance::make()->handle();

    expect($result)->toMatchArray([
        'region' => 'us-east',
        'queue' => 'checks.us-east',
        'admin_email' => null,
        'admin_created' => false,
    ])->and(User::query()->count())->toBe(0)
        ->and(Probe::query()->where('slug', 'us-east')->value('queue'))->toBe('checks.us-east');
});

it('reports the provisioned admin from the command', function () {
    config([
        'nominal.probe_region' => 'local',
        'nominal.admin.email' => 'admin@nominal.test',
        'nominal.admin.password' => 'generated-secret',
    ]);

    $this->artisan('nominal:provision')
        ->expectsOutputToContain('Default probe ready: local (checks.local).')
        ->expectsOutputToContain('Created admin admin@nominal.test.')
        ->assertSuccessful();
});
