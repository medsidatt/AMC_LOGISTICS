<?php

use App\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

/**
 * URL-triggered cron endpoints (for Infomaniak shared hosting where only
 * "URL cron" is available). Protected by the CRON_TOKEN env var; pass it as
 * ?token=… or X-Cron-Token header.
 *
 * Usage:
 *   https://your-domain.com/cron/run?token=SECRET                  → schedule:run
 *   https://your-domain.com/cron/run/<slug>?token=SECRET            → single job
 *
 * See App\Http\Controllers\CronController::JOBS for the slug whitelist.
 */
Route::group(['prefix' => 'cron', 'as' => 'cron.'], function () {
    Route::get('/run', [CronController::class, 'run'])->name('run');
    Route::get('/run/{job}', [CronController::class, 'runJob'])
        ->where('job', '[a-z0-9-]+')
        ->name('run.job');
});
