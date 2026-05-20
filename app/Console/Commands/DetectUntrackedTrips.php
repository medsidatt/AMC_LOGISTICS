<?php

namespace App\Console\Commands;

use App\Services\UntrackedTripDetector;
use Illuminate\Console\Command;

class DetectUntrackedTrips extends Command
{
    protected $signature = 'logistics:detect-untracked-trips
        {--days=7 : Look-back window in days}';

    protected $description = 'Flag freight loops (parking → provider → client → parking) without a bon de transport.';

    public function handle(UntrackedTripDetector $detector): int
    {
        $days = (int) $this->option('days');
        $this->info("Scanning the last {$days} day(s) for untracked freight trips…");

        $incidents = $detector->runOverWindow($days);

        $this->info(sprintf('Opened %d incident(s).', count($incidents)));

        return self::SUCCESS;
    }
}
