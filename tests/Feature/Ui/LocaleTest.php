<?php

use App\Models\Profile;
use App\Models\User;
use App\View\Format;
use Illuminate\Support\Carbon;

it('defaults to Arabic, right-to-left', function () {
    expect(config('app.locale'))->toBe('ar')
        ->and(config('app.fallback_locale'))->toBe('en');

    $this->get('/login')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false);
});

it('uses the session locale for guests', function () {
    $this->withSession(['locale' => 'en'])
        ->get('/login')
        ->assertSee('<html lang="en" dir="ltr">', false)
        ->assertSee(__('auth.login.heading', [], 'en'));
});

it('prefers the signed-in user locale over the session', function () {
    $user = User::factory()->create(['locale' => 'en']);
    Profile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->withSession(['locale' => 'ar'])
        ->get('/profile')
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr">', false);
});

it('ignores an unsupported locale', function () {
    $this->withSession(['locale' => 'fr'])
        ->get('/login')
        ->assertSee('<html lang="ar" dir="rtl">', false);
});

it('switches the locale for a guest and remembers it in the session', function () {
    $this->from('/login')
        ->post(route('locale.switch'), ['locale' => 'en'])
        ->assertRedirect('/login')
        ->assertSessionHas('locale', 'en');

    $this->get('/login')->assertSee('dir="ltr"', false);
});

it('switches the locale for a signed-in user and saves it on the account', function () {
    $user = User::factory()->create(['locale' => 'ar']);
    Profile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->from('/profile')
        ->post(route('locale.switch'), ['locale' => 'en'])
        ->assertRedirect('/profile')
        ->assertSessionHas('locale', 'en');

    expect($user->fresh()->locale)->toBe('en');

    $this->get('/profile')->assertSee('dir="ltr"', false);
});

it('rejects an unsupported locale on switch', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $this->actingAs($user)
        ->post(route('locale.switch'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');

    expect($user->fresh()->locale)->toBe('ar');
});

it('keeps Carbon on the matching locale with Levantine month names', function () {
    app()->setLocale('ar');
    expect(Carbon::getLocale())->toBe('ar_SY')
        ->and(Format::date('2026-01-05', 'long'))->toBe('الاثنين 5 كانون الثاني 2026');

    app()->setLocale('en');
    expect(Carbon::getLocale())->toBe('en')
        ->and(Format::date('2026-01-05', 'long'))->toBe('Monday 5 January 2026');
});

it('formats numbers with Western digits in both locales', function () {
    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);

        expect(Format::number(1234.5, 1))->toBe('1,234.5')
            ->and(Format::number(12.0, 1))->toBe('12')
            ->and(Format::number(12.0, 1, fixed: true))->toBe('12.0')
            ->and(Format::number(0.04, 1))->toBe('0')
            ->and(Format::number(null))->toBe('—')
            ->and(Format::westernDigits('١٢٫٥ غ'))->toBe('12.5 غ');
    }

    app()->setLocale('ar');

    expect(Blade::render('@num(1500) / @num(2.25, 1) / @date("2026-03-09", "short")'))
        ->toBe('1,500 / 2.3 / 9 آذار');
});

it('reports direction per locale', function () {
    expect(Format::dir('ar'))->toBe('rtl')
        ->and(Format::dir('en'))->toBe('ltr');
});
