<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/** Sign-up: one user owns one new workspace on the trial plan. */
class AccountService
{
    public function register(string $name, string $business, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $business, $email, $password) {
            $workspace = Workspace::query()->create([
                'name' => trim($business),
                'slug' => Workspace::uniqueSlug($business),
                'sender_name' => trim($name),
                'plan' => (string) config('plans.trial_plan', 'percubaan'),
                'trial_ends_at' => now()->addDays((int) config('plans.trial_days', 14)),
            ]);

            return User::query()->create([
                'workspace_id' => $workspace->id,
                'role' => 'owner',
                'name' => trim($name),
                'email' => mb_strtolower(trim($email)),
                'password' => $password,
            ]);
        });
    }
}
