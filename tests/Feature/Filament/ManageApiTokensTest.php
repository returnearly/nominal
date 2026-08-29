<?php

declare(strict_types=1);

use App\Filament\Clusters\Settings\Pages\ManageApiTokens;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

it('lists api tokens on the settings page', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);
    $user->createToken('terraform');

    $this->actingAs($user)
        ->get('/admin/settings/api-tokens')
        ->assertOk()
        ->assertSee('terraform')
        ->assertSee('owner@example.com');
});

it('creates an api token and shows the plaintext once', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ManageApiTokens::class)
        ->callAction('create', [
            'user_id' => $user->id,
            'name' => 'ci',
        ])
        ->assertNotified('API token created');

    $token = PersonalAccessToken::query()->where('name', 'ci')->first();

    expect($token)->not->toBeNull()
        ->and($token->tokenable_id)->toBe($user->id);
});

it('revokes an api token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('old')->accessToken;

    Livewire::actingAs($user)
        ->test(ManageApiTokens::class)
        ->callTableAction('delete', $token);

    expect(PersonalAccessToken::query()->whereKey($token->id)->exists())->toBeFalse();
});
