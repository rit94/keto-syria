<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Route;

function registerTestRoute(string $name, string $uri, array $middleware = ['web']): void
{
    Route::middleware($middleware)->get($uri, fn () => 'ok:'.$name)->name($name);

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
}

it('sends guests from home to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('sends guests away from auth-protected feature routes', function () {
    registerTestRoute('today', '/test-today', ['web', 'auth', 'onboarded']);

    $this->get('/test-today')->assertRedirect(route('login'));
});

it('redirects home to today for onboarded users', function () {
    $user = User::factory()->create();
    Profile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('today'));
});

it('registers the onboarded middleware alias', function () {
    expect(app('router')->getMiddleware())->toHaveKey('onboarded');
});

it('sends users without a profile to onboarding once it exists', function () {
    registerTestRoute('onboarding', '/test-onboarding', ['web', 'auth', 'onboarded']);
    registerTestRoute('today', '/test-today', ['web', 'auth', 'onboarded']);

    $user = User::factory()->create();

    $this->actingAs($user)->get('/test-today')->assertRedirect(route('onboarding'));
    $this->actingAs($user)->get('/')->assertRedirect(route('onboarding'));

    // The onboarding page itself must stay reachable (no redirect loop).
    $this->actingAs($user)->get('/test-onboarding')->assertOk();
});

it('lets onboarded users through', function () {
    registerTestRoute('onboarding', '/test-onboarding', ['web', 'auth']);
    registerTestRoute('today', '/test-today', ['web', 'auth', 'onboarded']);

    $user = User::factory()->create();
    Profile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/test-today')->assertOk()->assertSee('ok:today');
});
