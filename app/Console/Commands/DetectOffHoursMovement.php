<?php

namespace App\Console\Commands;

use App\Services\OffHoursMovementDetector;
use Illuminate\Console\Command;

class DetectOffHoursMovement extends Command
{
    protected $signature = 'logistics:detect-off-hours-movement
        {--window=120 : Minutes of telemetry to inspect}';

    protected $description = 'Scan recent telemetry for trucks moving outside work hours with no active transport.';

    public function handle(OffHoursMovementDetector $detector): int
    {
        $window = (int) $this->option('window');
        $this->info("Scanning the last {$window} minute(s) for off-hours movement…");

        $incidents = $detector->runOverRecentWindow($window);

        $this->info(sprintf('Opened %d incident(s).', count($incidents)));

        return self::SUCCESS;
    }
}
