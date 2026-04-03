<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
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
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
