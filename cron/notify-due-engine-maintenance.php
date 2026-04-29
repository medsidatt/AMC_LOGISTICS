<?php
// Frequency: every 15 minutes  →  */15 * * * *
require __DIR__ . '/_runner.php';
exit(run_cron('logistics:notify-due-engine-maintenance'));
