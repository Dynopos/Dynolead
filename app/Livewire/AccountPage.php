<?php

namespace App\Livewire;

use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Akaun')]
class AccountPage extends Component
{
    public string $business = '';

    public string $sender_name = '';

    public string $phone = '';

    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $new_password = '';

    public ?string $saved = null;

    public ?string $passwordSaved = null;

    public function mount(CurrentWorkspace $current): void
    {
        $workspace = $current->get();
        $user = auth()->user();

        $this->business = $workspace->name;
        $this->sender_name = (string) $workspace->sender_name;
        $this->phone = (string) $workspace->phone;
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function save(CurrentWorkspace $current): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'business' => 'required|string|max:120',
            'sender_name' => 'required|string|max:60',
            'phone' => 'nullable|string|max:30',
            'name' => 'required|string|max:80',
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
        ], ['required' => 'Medan ini wajib diisi.', 'email.unique' => 'E-mel ni dah digunakan.']);

        $current->get()->update([
            'name' => trim($data['business']),
            'sender_name' => trim($data['sender_name']),
            'phone' => trim($data['phone'] ?? '') ?: null,
        ]);
        $user->update(['name' => trim($data['name']), 'email' => mb_strtolower(trim($data['email']))]);

        $this->saved = 'Maklumat akaun disimpan.';
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => 'required|current_password',
            'new_password' => ['required', 'string', Password::min(8)],
        ], [
            'required' => 'Medan ini wajib diisi.',
            'current_password.current_password' => 'Kata laluan semasa salah.',
            'new_password.min' => 'Kata laluan sekurang-kurangnya 8 aksara.',
        ]);

        auth()->user()->update(['password' => Hash::make($this->new_password)]);
        $this->reset('current_password', 'new_password');
        $this->passwordSaved = 'Kata laluan ditukar.';
    }

    public function render()
    {
        return view('livewire.account-page');
    }
}
