<?php
// Frequency: daily at 02:30  →  30 2 * * *
require __DIR__ . '/_runner.php';
exit(run_cron('places:detect-hubs'));
