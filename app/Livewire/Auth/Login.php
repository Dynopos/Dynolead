<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Masuk')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = true;

    public function login()
    {
        $this->validate(
            ['email' => 'required|email', 'password' => 'required|string'],
            ['email.required' => 'Isi e-mel.', 'email.email' => 'E-mel tak sah.', 'password.required' => 'Isi kata laluan.'],
        );

        $key = 'login:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Terlalu banyak cubaan. Cuba lagi dalam '.RateLimiter::availableIn($key).' saat.');

            return null;
        }

        if (! Auth::attempt(['email' => Str::lower(trim($this->email)), 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            $this->addError('email', 'E-mel atau kata laluan salah.');

            return null;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return $this->redirectIntended(route('leads'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
