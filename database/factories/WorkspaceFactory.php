<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Workspace> */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = 'Kedai '.Str::random(6);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sender_name' => 'Ali',
            'plan' => 'pelanggan',
            'balance_sen' => 0,
            'trial_ends_at' => now()->addDays(14),
            'onboarded_at' => now(),
        ];
    }
}
