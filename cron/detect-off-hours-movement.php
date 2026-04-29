<?php
// Frequency: hourly  →  0 * * * *
require __DIR__ . '/_runner.php';
exit(run_cron('logistics:detect-off-hours-movement', ['--window' => 120]));
