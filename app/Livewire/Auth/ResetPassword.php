<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\View\Toast;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::guest')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $result = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user): void {
                $user->forceFill([
                    'password' => $this->password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($result !== Password::PASSWORD_RESET) {
            // An unknown address gets the same message as a bad token (no account enumeration).
            throw ValidationException::withMessages([
                'email' => __($result === Password::INVALID_USER ? Password::INVALID_TOKEN : $result),
            ]);
        }

        Toast::success(__('passwords.reset'));

        $this->redirect(route('login'));
    }

    public function render()
    {
        return view('livewire.auth.reset-password')->title(__('auth.reset.title'));
    }
}
