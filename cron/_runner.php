<?php

/**
 * Shared bootstrap + runner used by every cron/*.php file.
 *
 * Usage:
 *   require __DIR__ . '/_runner.php';
 *   run_cron('fleeti:sync-kilometers');
 *
 * Each individual file in this folder is registered as its own cron in
 * Infomaniak Manager > Advanced > Cron tasks, with its own frequency
 * (the schedule defined in bootstrap/app.php is bypassed in this mode —
 * Infomaniak's cron schedule is the source of truth).
 */

function run_cron(string $command, array $parameters = []): int
{
    $app = require __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    return $kernel->call($command, $parameters);
}

require __DIR__ . '/../vendor/autoload.php';
