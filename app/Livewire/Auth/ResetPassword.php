<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Tukar kata laluan')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => ['required', 'string', PasswordRule::min(8)],
        ], ['required' => 'Medan ini wajib diisi.', 'password.min' => 'Kata laluan sekurang-kurangnya 8 aksara.']);

        $status = Password::reset(
            ['email' => mb_strtolower(trim($this->email)), 'password' => $this->password, 'token' => $this->token],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->setRememberToken(str()->random(60));
                $user->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', 'Pautan dah tamat atau tak sah. Minta pautan baru.');

            return null;
        }

        session()->flash('status', 'Kata laluan dah ditukar. Sila masuk.');

        return $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
