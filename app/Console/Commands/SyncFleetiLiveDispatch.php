<?php

namespace App\Console\Commands;

use App\Repositories\TruckRepository;
use App\Services\FleetiSyncService;
use Illuminate\Console\Command;

/**
 * Fast-cadence polling of trucks currently on today's dispatch (or still
 * finishing yesterday's). Skips heavy odometer + engine-hour derivation —
 * those run in the 30-min fleeti:sync-kilometers lane.
 *
 * Scheduled at 1-min cadence during the quarry queue window, 2 min in
 * working hours, 5 min overnight.
 */
class SyncFleetiLiveDispatch extends Command
{
    protected $signature = 'fleeti:sync-live-dispatch {--customer-reference=} {--cadence=2} {--window-hours=18}';

    protected $description = 'Live polling for trucks on today\'s daily dispatch (fast lane).';

    public function __construct(
        private readonly FleetiSyncService $fleetiSyncService,
        private readonly TruckRepository $truckRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cadence = (int) $this->option('cadence');
        $windowHours = (int) $this->option('window-hours');

        $trucks = $this->truckRepository->getTrucksOnDispatchToday($windowHours);

        if ($trucks->isEmpty()) {
            $this->info('No dispatched trucks to poll right now.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Polling %d truck(s) at cadence ~%d min...',
            $trucks->count(),
            $cadence
        ));

        $summary = $this->fleetiSyncService->syncLive(
            $this->option('customer-reference'),
            $trucks
        );

        $this->table(['Metric', 'Value'], [
            ['Assets received', $summary['assets_received'] ?? 0],
            ['Trucks matched', $summary['trucks_matched'] ?? 0],
            ['Snapshots written', $summary['snapshots_created'] ?? 0],
            ['Fuel events', $summary['fuel_events_detected'] ?? 0],
            ['Stops closed', $summary['stops_closed'] ?? 0],
            ['Dispatch events created', $summary['dispatch_events_created'] ?? 0],
            ['Dispatches updated', $summary['dispatches_updated'] ?? 0],
            ['Assets skipped', $summary['assets_skipped'] ?? 0],
            ['Errors', count($summary['errors'] ?? [])],
        ]);

        if (! empty($summary['errors'])) {
            $this->warn('Some assets failed:');
            foreach ($summary['errors'] as $error) {
                $this->line('- ' . json_encode($error));
            }
        }

        return self::SUCCESS;
    }
}
