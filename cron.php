<?php

/**
 * Infomaniak cron entry point.
 *
 * Configure in Manager > Advanced > Cron tasks:
 *   File: /cron.php  (or full path: /sites/<your-site>/cron.php)
 *   Frequency: every minute
 *
 * Laravel's scheduler (defined in bootstrap/app.php) decides which commands
 * actually run on each tick.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->call('schedule:run');

exit($status);
