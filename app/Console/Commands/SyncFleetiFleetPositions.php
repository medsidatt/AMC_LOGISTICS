<?php

namespace App\Console\Commands;

use App\Models\Truck;
use App\Repositories\TruckRepository;
use App\Services\FleetiSyncService;
use Illuminate\Console\Command;

/**
 * Fleet-wide light position pass. ONE bulk Fleeti call per tick, no per-asset
 * detail. Keeps /logistics/fleet-map fresh for every active truck — not just
 * the ones on today's dispatch (which are handled by fleeti:sync-live-dispatch
 * with a deeper poll).
 */
class SyncFleetiFleetPositions extends Command
{
    protected $signature = 'fleeti:sync-fleet-positions {--customer-reference=}';

    protected $description = 'Light fleet-wide position refresh (one bulk Fleeti call, all active trucks).';

    public function __construct(
        private readonly FleetiSyncService $fleetiSyncService,
        private readonly TruckRepository $truckRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $allActive = Truck::query()
            ->where('is_active', true)
            ->whereNotNull('fleeti_asset_id')
            ->get();

        if ($allActive->isEmpty()) {
            $this->info('No active trucks with Fleeti asset IDs.');
            return self::SUCCESS;
        }

        $dispatchedIds = $this->truckRepository->getTrucksOnDispatchToday()->pluck('id');

        $this->info(sprintf(
            'Refreshing positions for %d active truck(s) (skipping %d on dispatch)...',
            $allActive->count(),
            $dispatchedIds->count()
        ));

        $summary = $this->fleetiSyncService->syncFleetPositions(
            $this->option('customer-reference'),
            $allActive,
            $dispatchedIds
        );

        $this->table(['Metric', 'Value'], [
            ['Assets received', $summary['assets_received'] ?? 0],
            ['Trucks matched', $summary['trucks_matched'] ?? 0],
            ['Snapshots written', $summary['snapshots_created'] ?? 0],
            ['Stops closed', $summary['stops_closed'] ?? 0],
            ['Skipped (on dispatch)', $summary['dispatched_skipped'] ?? 0],
            ['Skipped (no match)', $summary['assets_skipped'] ?? 0],
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
