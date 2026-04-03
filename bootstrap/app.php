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
            \App\Http\Middleware\SetLocale::class,
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

        $schedule->command('logistics:notify-missing-daily-checklists')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->create();
