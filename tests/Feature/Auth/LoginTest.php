<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

it('renders the login screen', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee(__('auth.login.heading', [], 'ar'))
        ->assertSeeLivewire(Login::class);
});

it('logs a user in with valid credentials', function () {
    $user = User::factory()->create(['locale' => 'en']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect(session('locale'))->toBe('en');
});

it('returns the user to the page they originally asked for', function () {
    $user = User::factory()->create();

    session()->put('url.intended', url('/somewhere'));

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(url('/somewhere'));
});

it('rejects a wrong password with a localized message', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee(__('auth.failed', [], 'ar'))
        ->assertSet('password', '');

    $this->assertGuest();
});

it('requires an email and a password', function () {
    Livewire::test(Login::class)
        ->call('login')
        ->assertHasErrors(['email' => 'required', 'password' => 'required'])
        ->assertSee('كلمة المرور مطلوب.');
});

it('sets a remember token when "remember me" is ticked', function () {
    $user = User::factory()->create(['remember_token' => null]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->set('remember', true)
        ->call('login')
        ->assertHasNoErrors();

    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('locks out after five failed attempts', function () {
    Event::fake([Lockout::class]);

    $user = User::factory()->create();

    $component = Livewire::test(Login::class)->set('email', $user->email);

    foreach (range(1, Login::MAX_ATTEMPTS) as $attempt) {
        $component->set('password', 'wrong-password')->call('login')->assertSee(__('auth.failed', [], 'ar'));
    }

    // Even the correct password is refused while locked out.
    $component->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee('محاولات دخول كثيرة');

    $this->assertGuest();
    Event::assertDispatched(Lockout::class);
});

it('clears the failed-attempt counter after a successful login', function () {
    $user = User::factory()->create();

    $component = Livewire::test(Login::class)->set('email', $user->email);

    foreach (range(1, Login::MAX_ATTEMPTS - 1) as $attempt) {
        $component->set('password', 'wrong-password')->call('login');
    }

    $component->set('password', 'password')->call('login')->assertHasNoErrors();
    auth()->logout();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertSee(__('auth.failed', [], 'ar'));
});

it('sends signed-in users away from the login screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect(route('home'));
});
