<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

it('renders the forgot-password screen', function () {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertSee(__('auth.forgot.heading', [], 'ar'))
        ->assertSeeLivewire(ForgotPassword::class);
});

it('emails a reset link to a registered address', function () {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink')
        ->assertHasNoErrors()
        ->assertSee(__('passwords.sent', [], 'ar'))
        ->assertSet('email', '');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('gives the same answer for an unknown address without sending anything', function () {
    Notification::fake();

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@example.com')
        ->call('sendResetLink')
        ->assertHasNoErrors()
        ->assertSee(__('passwords.sent', [], 'ar'));

    Notification::assertNothingSent();
});

it('validates the email on the forgot-password form', function () {
    Livewire::test(ForgotPassword::class)
        ->set('email', 'nope')
        ->call('sendResetLink')
        ->assertHasErrors(['email' => 'email']);
});

it('rate limits reset-link requests', function () {
    Notification::fake();

    $component = Livewire::test(ForgotPassword::class);

    foreach (range(1, ForgotPassword::MAX_ATTEMPTS) as $attempt) {
        $component->set('email', "person{$attempt}@example.com")->call('sendResetLink')->assertHasNoErrors();
    }

    $component->set('email', 'one-more@example.com')
        ->call('sendResetLink')
        ->assertHasErrors('email')
        ->assertSee(__('passwords.throttled', [], 'ar'));
});

it('renders the reset screen from the emailed link', function () {
    Notification::fake();

    $user = User::factory()->create();
    Password::sendResetLink(['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee(__('auth.reset.heading', [], 'ar'))
            ->assertSee($user->email)
            ->assertSeeLivewire(ResetPassword::class);

        return true;
    });
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    Livewire::withQueryParams(['email' => $user->email])
        ->test(ResetPassword::class, ['token' => $token])
        ->assertSet('email', $user->email)
        ->set('password', 'brand-new-pass-1')
        ->set('password_confirmation', 'brand-new-pass-1')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('brand-new-pass-1', $user->fresh()->password))->toBeTrue();

    $this->get('/login')->assertSee(__('passwords.reset', [], 'ar'));
});

it('refuses an invalid token', function () {
    $user = User::factory()->create();

    Livewire::test(ResetPassword::class, ['token' => 'not-a-real-token'])
        ->set('email', $user->email)
        ->set('password', 'brand-new-pass-1')
        ->set('password_confirmation', 'brand-new-pass-1')
        ->call('resetPassword')
        ->assertHasErrors('email')
        ->assertSee(__('passwords.token', [], 'ar'));

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('does not reveal whether an address exists when resetting', function () {
    Livewire::test(ResetPassword::class, ['token' => 'anything'])
        ->set('email', 'nobody@example.com')
        ->set('password', 'brand-new-pass-1')
        ->set('password_confirmation', 'brand-new-pass-1')
        ->call('resetPassword')
        ->assertHasErrors('email')
        ->assertSee(__('passwords.token', [], 'ar'));
});

it('requires a confirmed password of sufficient length when resetting', function () {
    $user = User::factory()->create();

    Livewire::test(ResetPassword::class, ['token' => Password::createToken($user)])
        ->set('email', $user->email)
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('resetPassword')
        ->assertHasErrors('password')
        ->assertSee('كلمة المرور يجب أن يكون 8 أحرف على الأقل.');
});
