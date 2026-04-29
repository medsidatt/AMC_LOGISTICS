<?php
// Frequency: every 30 minutes  →  */30 * * * *
require __DIR__ . '/_runner.php';
exit(run_cron('fleeti:sync-kilometers'));
