<?php

namespace App\Livewire;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Masuk')]
class Login extends Component
{
    public string $password = '';

    public function login()
    {
        $key = 'login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('password', 'Terlalu banyak cubaan. Cuba lagi sekejap lagi.');

            return null;
        }

        $expected = (string) config('dynoleads.login_password');

        if ($expected === '') {
            $this->addError('password', 'APP_LOGIN_PASSWORD belum diset dalam .env.');

            return null;
        }

        if (! hash_equals($expected, $this->password)) {
            RateLimiter::hit($key, 60);
            $this->addError('password', 'Kata laluan salah.');

            return null;
        }

        RateLimiter::clear($key);
        session()->regenerate();
        session()->put('owner', true);

        return $this->redirectRoute('leads');
    }

    public function render()
    {
        return view('livewire.login');
    }
}
