<?php

use App\Jobs\PurgePlaceCacheJob;
use Illuminate\Support\Facades\Schedule;

// Google Places content is temporary (spec §6): purge expired cache daily.
Schedule::job(new PurgePlaceCacheJob)->dailyAt('03:15')->name('purge-place-cache')->withoutOverlapping();

// Hourly safety net in case the server was down at 03:15.
Schedule::job(new PurgePlaceCacheJob)->hourly()->name('purge-place-cache-hourly')->withoutOverlapping();
