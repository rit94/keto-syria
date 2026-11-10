<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\View\Toast;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::guest')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        // The account keeps the language the visitor was using when they signed up.
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'locale' => app()->getLocale(),
        ]);

        event(new Registered($user));

        Auth::login($user);

        session()->regenerate();
        session()->put('locale', $user->locale);

        Toast::success(__('auth.register.welcome'));

        $this->redirect(route('home'));
    }

    public function render()
    {
        return view('livewire.auth.register')->title(__('auth.register.title'));
    }
}
