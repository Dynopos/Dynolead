<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/** Sign-up: one user owns one new workspace, on the free trial. */
class AccountService
{
    public function register(string $name, string $business, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $business, $email, $password) {
            $workspace = Workspace::query()->create([
                'name' => trim($business),
                'slug' => Workspace::uniqueSlug($business),
                'sender_name' => trim($name),
                'plan' => 'pelanggan',
                'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
            ]);

            $user = User::query()->create([
                'workspace_id' => $workspace->id,
                'role' => 'owner',
                'name' => trim($name),
                'email' => mb_strtolower(trim($email)),
                'password' => $password,
            ]);

            return $user;
        });
    }
}
