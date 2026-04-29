<?php
// Frequency: daily at 02:45  →  45 2 * * *
require __DIR__ . '/_runner.php';
exit(run_cron('logistics:rebuild-trip-segments', ['--days' => 7]));
