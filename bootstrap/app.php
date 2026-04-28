<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load additional web route files from routes/web/*.php
            $routeFiles = glob(base_path('routes/web/*.php'));
            foreach ($routeFiles as $routeFile) {
                \Illuminate\Support\Facades\Route::middleware('web')
                    ->group($routeFile);
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\CheckPasswordChange::class,
        ]);

        $middleware->alias([
            'ajax' => \App\Http\Middleware\AjaxRequestOnly::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'horizontal' => \App\Http\Middleware\MenuType::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        \SocialiteProviders\Manager\ServiceProvider::class,
        \Laravel\Socialite\SocialiteServiceProvider::class,
        \Barryvdh\DomPDF\ServiceProvider::class,
    ])
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('fleeti:sync-kilometers')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('logistics:notify-due-engine-maintenance')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('logistics:notify-missing-weekly-checklists')
            ->mondays()
            ->at('07:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Monthly telemetry snapshot compaction:
        // keeps 1/hour per truck after 90 days, 1/day per truck after 365 days.
        // Tracking rows (km/fuel/engine-hours) are preserved — only raw snapshots are pruned.
        $schedule->command('telemetry:compact')
            ->monthlyOn(1, '03:15')
            ->withoutOverlapping()
            ->runInBackground();

        // Theft-detection layer (Phase A)
        // ---------------------------------------------------------------
        // Nightly: cluster long parked sessions into auto-detected "base" places.
        $schedule->command('places:detect-hubs')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Nightly: rebuild trip segments for the last 7 days of transports
        // (catches transports created/validated after the related telemetry).
        $schedule->command('logistics:rebuild-trip-segments --days=7')
            ->dailyAt('02:45')
            ->withoutOverlapping()
            ->runInBackground();

        // Hourly: scan recent telemetry for off-hours movement.
        $schedule->command('logistics:detect-off-hours-movement --window=120')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->create();
