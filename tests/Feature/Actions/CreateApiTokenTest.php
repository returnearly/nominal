<?php

declare(strict_types=1);

use App\Actions\CreateApiToken;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('creates a sanctum token that authenticates graphql', function () {
    $user = User::factory()->create();

    $plain = CreateApiToken::make()->handle($user, 'terraform');

    expect($plain)->toContain('|')
        ->and($user->tokens()->where('name', 'terraform')->exists())->toBeTrue();

    $this->postJson('/graphql', [
        'query' => '{ __typename }',
    ], [
        'Authorization' => 'Bearer '.$plain,
    ])->assertSuccessful()
        ->assertJsonPath('data.__typename', 'Query');
});

it('creates a token from the cli command', function () {
    $user = User::factory()->create(['email' => 'ci@example.com']);

    $this->artisan('nominal:token', [
        'email' => $user->email,
        '--name' => 'ci',
    ])->assertSuccessful();

    expect($user->tokens()->where('name', 'ci')->exists())->toBeTrue();
});

it('fails the cli command when the user does not exist', function () {
    $this->artisan('nominal:token', ['email' => 'missing@example.com'])
        ->assertFailed();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});
