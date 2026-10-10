<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspace;
use Database\Seeders\ProductSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Create (or promote) the platform admin with their own workspace. */
class MakeAdmin extends Command
{
    protected $signature = 'dynoleads:admin
        {email : E-mel admin}
        {--name=Bob : Nama admin}
        {--business=DynoPOS Technologies : Nama workspace admin}
        {--demo-products : Seed DynoPOS dan murahwebsite.my ke workspace admin}';

    protected $description = 'Cipta atau naikkan akaun admin platform (Bob)';

    public function handle(CurrentWorkspace $current): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null && ! $this->input->isInteractive()) {
            // Forge "Commands" cannot answer a password prompt.
            $this->error("Tiada akaun dengan e-mel {$email}. Semak ejaan, atau daftar dahulu di /daftar dan jalankan semula.");

            return self::FAILURE;
        }

        if ($user === null) {
            $password = (string) $this->secret('Kata laluan (min 8 aksara)');
            if ($password === '') {
                // No answer (e.g. Forge "Commands"): most likely a typo in the e-mail.
                $this->error("Tiada akaun dengan e-mel {$email}. Semak ejaan, atau daftar dahulu di /daftar dan jalankan semula.");

                return self::FAILURE;
            }
            if (mb_strlen($password) < 8) {
                $this->error('Kata laluan terlalu pendek.');

                return self::FAILURE;
            }

            $user = DB::transaction(function () use ($email, $password) {
                // Fasa 0 data moved by migration into an internal workspace with no owner: take it over.
                $workspace = Workspace::query()->where('plan', 'dalaman')->whereDoesntHave('users')->oldest('id')->first()
                    ?? Workspace::query()->create([
                        'name' => (string) $this->option('business'),
                        'slug' => Workspace::uniqueSlug((string) $this->option('business')),
                        'sender_name' => (string) $this->option('name'),
                        'plan' => 'dalaman',
                        'onboarded_at' => now(),
                    ]);

                return User::query()->create([
                    'workspace_id' => $workspace->id,
                    'role' => 'owner',
                    'name' => (string) $this->option('name'),
                    'email' => $email,
                    'password' => $password,
                ]);
            });
        }

        $user->forceFill(['is_admin' => true])->save();

        // An admin who signed up at /daftar first (no SSH needed): their own workspace becomes internal.
        if ($user->workspace && $user->workspace->plan !== 'dalaman') {
            $user->workspace->forceFill(['plan' => 'dalaman'])->save();
        }

        if ($this->option('demo-products') && $user->workspace_id) {
            $current->runAs($user->workspace_id, fn () => (new ProductSeeder)->run());
            $user->workspace->forceFill(['onboarded_at' => now()])->save();
        }

        $this->info("Admin: {$user->email} (workspace #{$user->workspace_id}).");

        return self::SUCCESS;
    }
}
