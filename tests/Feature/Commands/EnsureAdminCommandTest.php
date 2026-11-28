<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Output\BufferedOutput;

it('creates an admin and prints the login when none exists', function () {
    $output = ensureAdmin();

    $user = User::query()->sole();
    $password = printedPassword($output);

    expect($user->email)->toBe('admin@nominal.local')
        ->and($user->name)->toBe('Nominal Admin')
        ->and($output)->toContain('Email: admin@nominal.local')
        ->and(Hash::check($password, $user->password))->toBeTrue()
        ->and(Auth::attempt([
            'email' => $user->email,
            'password' => $password,
        ]))->toBeTrue();
});

it('leaves an existing login unchanged', function () {
    $existing = User::factory()->create();
    $passwordHash = $existing->password;

    $output = ensureAdmin();

    $existing->refresh();

    expect(User::query()->count())->toBe(1)
        ->and($existing->password)->toBe($passwordHash)
        ->and($output)->toContain('Admin login already exists.')
        ->and($output)->not->toContain('Password:');
});

function ensureAdmin(): string
{
    $output = new BufferedOutput;

    expect(Artisan::call('nominal:ensure-admin', [], $output))->toBe(0);

    return $output->fetch();
}

function printedPassword(string $output): string
{
    preg_match('/Password: (\S+)/', $output, $matches);

    expect($matches[1] ?? null)->toBeString()->not->toBeEmpty();

    return $matches[1];
}
