<?php

use App\Jobs\PurgePlaceCacheJob;
use App\Jobs\SyncPendingPaymentsJob;
use Illuminate\Support\Facades\Schedule;

// Google Places content is temporary (spec §6): purge expired cache daily.
Schedule::job(new PurgePlaceCacheJob)->dailyAt('03:15')->name('purge-place-cache')->withoutOverlapping();

// Hourly safety net in case the server was down at 03:15.
Schedule::job(new PurgePlaceCacheJob)->hourly()->name('purge-place-cache-hourly')->withoutOverlapping();

// Billing: pick up CHIP payments whose callback did not arrive.
Schedule::job(new SyncPendingPaymentsJob)->hourly()->name('sync-pending-payments')->withoutOverlapping();

// Safety net for the queue worker: every minute, work the queue until it is empty. If the
// Forge worker is down, searches still run (at most ~1 minute late per step). If it is up,
// both share the queue safely: a reserved job is not taken again before retry_after (900s).
if (config('dynoleads.queue_via_scheduler')) {
    Schedule::command('queue:work database --queue=default --stop-when-empty --max-time=50 --timeout=660 --tries=2')
        ->everyMinute()
        ->name('queue-safety-net')
        ->withoutOverlapping(15)
        ->runInBackground();
}
