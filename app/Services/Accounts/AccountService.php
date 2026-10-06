<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\CreditService;
use Illuminate\Support\Facades\DB;

/** Sign-up: one user owns one new workspace, with free starter credits. */
class AccountService
{
    public function __construct(private CreditService $credits) {}

    public function register(string $name, string $business, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $business, $email, $password) {
            $workspace = Workspace::query()->create([
                'name' => trim($business),
                'slug' => Workspace::uniqueSlug($business),
                'sender_name' => trim($name),
                'plan' => 'kredit',
            ]);

            $user = User::query()->create([
                'workspace_id' => $workspace->id,
                'role' => 'owner',
                'name' => trim($name),
                'email' => mb_strtolower(trim($email)),
                'password' => $password,
            ]);

            $bonus = (int) config('credits.signup_bonus', 0);
            if ($bonus > 0) {
                $this->credits->grant($workspace, $bonus, 'signup_bonus', 'Kredit percuma pendaftaran', userId: $user->id);
            }

            return $user;
        });
    }
}
