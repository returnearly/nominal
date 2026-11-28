<?php

declare(strict_types=1);

use App\Models\User;

it('redirects the home page to the admin panel', function () {
    $this->get('/')->assertRedirect('/admin');
});

it('exposes a health endpoint', function () {
    $this->get('/up')->assertOk();
});

it('trusts the proxy forwarded protocol', function () {
    $this->withHeader('X-Forwarded-Proto', 'https')
        ->get('/up')
        ->assertOk();

    expect(request()->isSecure())->toBeTrue();
});

it('requires authentication for the admin panel', function () {
    $this->get('/admin')->assertRedirect();

    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertRedirect('/admin/monitors');
});
