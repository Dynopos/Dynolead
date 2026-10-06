<?php

namespace App\Providers;

use App\Http\Middleware\SetCurrentWorkspace;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentWorkspace::class);
    }

    public function boot(): void
    {
        // A queue worker lives for many jobs: never carry one customer's context into the next.
        Queue::looping(fn () => $this->app->make(CurrentWorkspace::class)->clear());

        // Livewire update requests must run inside the same workspace as the page.
        Livewire::addPersistentMiddleware([SetCurrentWorkspace::class]);

        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Tukar kata laluan Dyno Leads')
            ->greeting('Salam '.$user->name.',')
            ->line('Kami terima permintaan untuk tukar kata laluan akaun anda.')
            ->action('Tukar kata laluan', route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->line('Pautan ini tamat dalam '.config('auth.passwords.users.expire').' minit.')
            ->line('Kalau anda tak minta, abaikan e-mel ini.')
            ->salutation('Dyno Leads'));
    }
}
