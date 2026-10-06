<?php

namespace App\Livewire\Auth;

use App\Services\Accounts\AccountService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Daftar')]
class Register extends Component
{
    public string $name = '';

    public string $business = '';

    public string $email = '';

    public string $password = '';

    public bool $agree = false;

    public function register(AccountService $accounts)
    {
        $key = 'register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Terlalu banyak pendaftaran dari rangkaian ini. Cuba lagi nanti.');

            return null;
        }

        $data = $this->validate([
            'name' => 'required|string|max:80',
            'business' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:190|unique:users,email',
            'password' => ['required', 'string', Password::min(8)],
            'agree' => 'accepted',
        ], [
            'required' => 'Medan ini wajib diisi.',
            'email.email' => 'E-mel tak sah.',
            'email.unique' => 'E-mel ni dah didaftarkan. Cuba masuk.',
            'password.min' => 'Kata laluan sekurang-kurangnya 8 aksara.',
            'agree.accepted' => 'Sila setuju dengan Terma dan Polisi Privasi.',
        ]);

        RateLimiter::hit($key, 3600);

        $user = $accounts->register($data['name'], $data['business'], $data['email'], $data['password']);

        Auth::login($user, remember: true);
        session()->regenerate();

        return $this->redirectRoute('leads');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
