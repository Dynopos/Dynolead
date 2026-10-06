<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature tests run against an in-memory SQLite database. No test may call a
| real external API: every HTTP call must be faked with Http::fake().
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit');

/** Log in as the single Fasa 0 owner. */
function actingAsOwner(): Tests\TestCase
{
    return test()->withSession(['owner' => true]);
}
