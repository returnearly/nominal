<?php

declare(strict_types=1);

use App\Filament\Clusters\Settings\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\Settings\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\Settings\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('lists users on the settings users page', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $this->actingAs($user)
        ->get('/admin/settings/users')
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('ada@example.com');

    Livewire::actingAs($user)
        ->test(ListUsers::class)
        ->assertCanSeeTableRecords([$user]);
});

it('creates a user from settings', function () {
    $actor = User::factory()->create();

    Livewire::actingAs($actor)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('email', 'grace@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('Grace Hopper')
        ->and(Hash::check('secret-password', $created->password))->toBeTrue();
});

it('updates a user and only changes the password when provided', function () {
    $actor = User::factory()->create();
    $user = User::factory()->create([
        'name' => 'Original',
        'email' => 'original@example.com',
        'password' => 'unchanged',
    ]);

    Livewire::actingAs($actor)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'name' => 'Renamed',
            'email' => 'renamed@example.com',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Renamed')
        ->and($user->email)->toBe('renamed@example.com')
        ->and(Hash::check('unchanged', $user->password))->toBeTrue();

    Livewire::actingAs($actor)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'password' => 'new-secret',
            'password_confirmation' => 'new-secret',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-secret', $user->fresh()->password))->toBeTrue();
});

it('does not allow deleting the authenticated user', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create();

    Livewire::actingAs($actor)
        ->test(EditUser::class, ['record' => $actor->getRouteKey()])
        ->assertActionHidden('delete');

    expect($actor->fresh())->not->toBeNull();

    Livewire::actingAs($actor)
        ->test(EditUser::class, ['record' => $other->getRouteKey()])
        ->callAction('delete');

    expect(User::query()->whereKey($other->id)->exists())->toBeFalse();
});
