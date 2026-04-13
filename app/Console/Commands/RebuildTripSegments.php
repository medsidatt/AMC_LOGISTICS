<?php

namespace App\Console\Commands;

use App\Models\TransportTracking;
use App\Services\TripSegmentBuilderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RebuildTripSegments extends Command
{
    protected $signature = 'logistics:rebuild-trip-segments
        {--since= : Only rebuild transports whose client_date or provider_date is >= this date (YYYY-MM-DD)}
        {--days=7 : Default rebuild window in days when --since is not given}';

    protected $description = 'Rebuild trip_segment rows for recent transport_trackings.';

    public function handle(TripSegmentBuilderService $builder): int
    {
        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))->startOfDay()
            : Carbon::now()->subDays((int) $this->option('days'))->startOfDay();

        $this->info("Rebuilding trip segments for transports since {$since->toDateString()}…");

        $query = TransportTracking::query()
            ->whereNotNull('truck_id')
            ->where(function ($q) use ($since) {
                $q->whereDate('provider_date', '>=', $since)
                    ->orWhereDate('client_date', '>=', $since);
            })
            ->orderBy('id');

        $built = 0;
        $skipped = 0;

        $query->chunkById(200, function ($chunk) use ($builder, &$built, &$skipped) {
            foreach ($chunk as $tt) {
                $segment = $builder->buildForTransport($tt);
                if ($segment) {
                    $built++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Built {$built} segment(s). Skipped {$skipped} (missing truck or dates).");

        return self::SUCCESS;
    }
}
