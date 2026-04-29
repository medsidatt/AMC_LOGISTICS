<?php
// Frequency: 1st of month at 03:15  →  15 3 1 * *
require __DIR__ . '/_runner.php';
exit(run_cron('telemetry:compact'));
