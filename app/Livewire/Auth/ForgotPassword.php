<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Lupa kata laluan')]
class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $sent = null;

    public function send(): void
    {
        $this->validate(['email' => 'required|email'], ['email.required' => 'Isi e-mel.', 'email.email' => 'E-mel tak sah.']);

        $key = 'forgot:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Terlalu banyak cubaan. Cuba lagi nanti.');

            return;
        }
        RateLimiter::hit($key, 600);

        Password::sendResetLink(['email' => mb_strtolower(trim($this->email))]);

        // Same answer whether or not the e-mail exists (no account enumeration).
        $this->sent = 'Kalau e-mel ni berdaftar, pautan tukar kata laluan dah dihantar. Semak peti masuk anda.';
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
