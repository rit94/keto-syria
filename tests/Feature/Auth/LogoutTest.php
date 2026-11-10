<?php

use App\Models\User;

it('logs the user out and returns to login', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('keeps the interface language after logging out', function () {
    $this->actingAs(User::factory()->create(['locale' => 'en']))
        ->post('/logout')
        ->assertSessionHas('locale', 'en');

    $this->get('/login')->assertSee('dir="ltr"', false);
});

it('does not allow logging out with GET', function () {
    $this->actingAs(User::factory()->create())
        ->get('/logout')
        ->assertStatus(405);

    $this->assertAuthenticated();
});

it('sends guests who post to logout to the login screen', function () {
    $this->post('/logout')->assertRedirect(route('login'));
});
