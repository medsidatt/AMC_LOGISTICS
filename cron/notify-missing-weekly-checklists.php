<?php
// Frequency: Mondays at 07:00  →  0 7 * * 1
require __DIR__ . '/_runner.php';
exit(run_cron('logistics:notify-missing-weekly-checklists'));
