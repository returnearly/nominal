<?php

declare(strict_types=1);

use App\Actions\ProvisionCloudflareUser;
use App\Auth\CloudflareUserResolver;
use App\Enums\InterfaceAuth;
use App\Http\Middleware\AuthenticateAnonymousOperator;
use App\Http\Middleware\LoginCloudflareInterfaceUser;
use App\Models\User;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use ReturnEarly\CloudflareZeroTrust\Contracts\ApplicationUserResolver;
use ReturnEarly\CloudflareZeroTrust\Http\Middleware\AuthenticateCloudflareAccess;
use ReturnEarly\CloudflareZeroTrust\Jwt\VerifiedAccessToken;
use ReturnEarly\CloudflareZeroTrust\Principals\UserPrincipal;
use Symfony\Component\HttpFoundation\Response;

it('defaults to password login when the interface auth method is missing or invalid', function () {
    config(['nominal.interface_auth' => 'nope']);

    expect(InterfaceAuth::current())->toBe(InterfaceAuth::Login);
});

it('redirects guests to the admin login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('shows the filament login page', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Sign in')
        ->assertSee('Nominal')
        ->assertSee('og.jpg', false);
});

it('does not require a named login route when a session is invalidated', function () {
    expect(Route::has('login'))->toBeFalse();

    Route::middleware(['web', AuthenticateSession::class])
        ->get('/__session-auth', fn () => 'ok');

    $this->actingAs(User::factory()->create());
    session()->put('password_hash_web', 'invalid');

    $this->get('/__session-auth')->assertRedirect('/admin/login');
});

it('logs in the anonymous operator before filament authenticates the request', function () {
    Route::middleware([
        'web',
        Authenticate::class,
        AuthenticateAnonymousOperator::class,
    ])->get('/__operator-before-filament', fn () => auth()->user()->email);

    $this->get('/__operator-before-filament')
        ->assertOk()
        ->assertSee('operator@nominal.local');
});

it('logs in a local operator when interface authentication is disabled', function () {
    registerAnonymousOperatorRoute(fn () => auth()->user()->email);

    $this->get('/__anonymous-operator')
        ->assertOk()
        ->assertSee('operator@nominal.local');

    expect(User::query()->where('email', 'operator@nominal.local')->exists())->toBeTrue();
});

it('reuses the existing anonymous operator instead of creating duplicates', function () {
    $existing = User::factory()->create([
        'email' => 'operator@nominal.local',
        'name' => 'Existing Operator',
    ]);

    registerAnonymousOperatorRoute(fn () => (string) auth()->id());

    $this->get('/__anonymous-operator')
        ->assertOk()
        ->assertSee((string) $existing->id);

    expect(User::query()->where('email', 'operator@nominal.local')->count())->toBe(1);
});

it('creates a filament user from a cloudflare access principal', function () {
    $user = ProvisionCloudflareUser::make()->handle(cloudflarePrincipal(
        email: 'tom.schlick@returnearly.net',
        name: null,
    ));

    expect($user)
        ->toBeInstanceOf(User::class)
        ->email->toBe('tom.schlick@returnearly.net')
        ->name->toBe('Tom Schlick')
        ->and(User::query()->where('email', 'tom.schlick@returnearly.net')->exists())->toBeTrue();
});

it('uses the cloudflare name claim when present', function () {
    $user = ProvisionCloudflareUser::make()->handle(cloudflarePrincipal(
        email: 'tom@returnearly.net',
        name: 'Tom Schlick',
    ));

    expect($user->name)->toBe('Tom Schlick');
});

it('reuses an existing user with the same email', function () {
    $existing = User::factory()->create([
        'email' => 'tom@returnearly.net',
        'name' => 'Existing',
    ]);

    $user = ProvisionCloudflareUser::make()->handle(cloudflarePrincipal(
        email: 'tom@returnearly.net',
        name: 'Cloudflare Name',
    ));

    expect($user->is($existing))->toBeTrue()
        ->and($user->name)->toBe('Existing')
        ->and(User::query()->where('email', 'tom@returnearly.net')->count())->toBe(1);
});

it('resolves cloudflare principals onto application users', function () {
    $resolver = app(CloudflareUserResolver::class);

    expect($resolver)->toBeInstanceOf(ApplicationUserResolver::class);

    $user = $resolver->resolve(cloudflarePrincipal(email: 'sso@returnearly.net'));

    expect($user)
        ->toBeInstanceOf(User::class)
        ->email->toBe('sso@returnearly.net');
});

it('logs the provisioned cloudflare user into the web guard', function () {
    $request = Request::create('/admin');
    $request->setLaravelSession($this->app->make('session.store'));
    $request->attributes->set(
        AuthenticateCloudflareAccess::REQUEST_ATTRIBUTE,
        verifiedAccessToken(cloudflarePrincipal(email: 'sso@returnearly.net')),
    );

    $response = app(LoginCloudflareInterfaceUser::class)->handle(
        $request,
        fn (): Response => response(auth()->user()->email),
    );

    expect($response->getContent())->toBe('sso@returnearly.net')
        ->and(auth()->user()?->email)->toBe('sso@returnearly.net');
});

it('rejects interface requests without a verified cloudflare user', function () {
    Route::middleware(['web', LoginCloudflareInterfaceUser::class])
        ->get('/__cloudflare-login', fn () => 'ok');

    $this->get('/__cloudflare-login')->assertUnauthorized();
});

function registerAnonymousOperatorRoute(callable $action): void
{
    Route::middleware(['web', AuthenticateAnonymousOperator::class])
        ->get('/__anonymous-operator', $action);
}

function cloudflarePrincipal(string $email, ?string $name = null): UserPrincipal
{
    $claims = $name === null ? [] : ['name' => $name];

    return new UserPrincipal(
        accountName: 'returnearly',
        applicationName: 'admin',
        issuer: 'https://returnearly.cloudflareaccess.com',
        audiences: ['test-aud'],
        subject: 'cf-'.$email,
        email: $email,
        country: 'US',
        identityNonce: null,
        claims: $claims,
    );
}

function verifiedAccessToken(UserPrincipal $principal): VerifiedAccessToken
{
    return new VerifiedAccessToken(
        token: 'test-jwt',
        header: ['kid' => 'test'],
        claims: $principal->claims(),
        principal: $principal,
        expiresAt: time() + 3600,
    );
}
