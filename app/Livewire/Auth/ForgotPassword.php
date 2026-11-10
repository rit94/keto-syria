<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::guest')]
class ForgotPassword extends Component
{
    public const MAX_ATTEMPTS = 5;

    public string $email = '';

    public ?string $status = null;

    public function sendResetLink(): void
    {
        $this->status = null;

        $this->validate(['email' => ['required', 'string', 'email']]);

        $key = 'password-reset:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['email' => __('passwords.throttled')]);
        }

        RateLimiter::hit($key);

        $result = Password::sendResetLink(['email' => $this->email]);

        if ($result === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => __('passwords.throttled')]);
        }

        // Same answer whether or not the address is registered (no account enumeration).
        $this->status = __('passwords.sent');
        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.auth.forgot-password')->title(__('auth.forgot.title'));
    }
}
