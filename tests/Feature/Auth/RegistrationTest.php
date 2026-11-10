<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Livewire\Livewire;

it('renders the registration screen in Arabic by default', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('auth.register.heading', [], 'ar'))
        ->assertSeeLivewire(Register::class);
});

it('registers a user, signs them in and redirects home', function () {
    Livewire::test(Register::class)
        ->set('name', 'سارة')
        ->set('email', 'sara@example.com')
        ->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'secret-pass-1')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::where('email', 'sara@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('سارة')
        ->and($user->locale)->toBe('ar')
        ->and(Hash::check('secret-pass-1', $user->password))->toBeTrue()
        ->and(session('locale'))->toBe('ar');
});

it('stores the current UI locale on the new account', function () {
    app()->setLocale('en');

    Livewire::test(Register::class)
        ->set('name', 'Sam')
        ->set('email', 'sam@example.com')
        ->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'secret-pass-1')
        ->call('register')
        ->assertHasNoErrors();

    expect(User::where('email', 'sam@example.com')->value('locale'))->toBe('en');
});

it('shows validation messages in Arabic', function () {
    Livewire::test(Register::class)
        ->set('name', '')
        ->set('email', 'not-an-email')
        ->set('password', 'short')
        ->set('password_confirmation', 'different')
        ->call('register')
        ->assertHasErrors(['name' => 'required', 'email' => 'email', 'password'])
        ->assertSee('الاسم مطلوب.')
        ->assertSee('البريد الإلكتروني يجب أن يكون بريداً إلكترونياً صالحاً.')
        ->assertSee('تأكيد كلمة المرور غير مطابق.');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('shows validation messages in English when the UI is English', function () {
    app()->setLocale('en');

    Livewire::test(Register::class)
        ->call('register')
        ->assertHasErrors(['name' => 'required'])
        ->assertSee('The name field is required.');
});

it('rejects an email that is already registered', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(Register::class)
        ->set('name', 'Someone')
        ->set('email', 'taken@example.com')
        ->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'secret-pass-1')
        ->call('register')
        ->assertHasErrors(['email' => 'unique'])
        ->assertSee('البريد الإلكتروني مستخدم من قبل.');
});

it('sends signed-in users away from the registration screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/register')
        ->assertRedirect(route('home'));
});
