<?php

namespace App\Console\Commands;

use App\Services\FleetiSyncService;
use Illuminate\Console\Command;

class SyncFleetiKilometers extends Command
{
    protected $signature = 'fleeti:sync-kilometers {--customer-reference=} {--all}';

    protected $description = 'Sync truck kilometer data from Fleeti API';

    public function __construct(private readonly FleetiSyncService $fleetiSyncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Syncing Fleeti kilometers...');

        $summary = $this->fleetiSyncService->syncKilometers(
            $this->option('customer-reference'),
            ! (bool) $this->option('all')
        );

        $this->table(['Metric', 'Value'], [
            ['Assets received', $summary['assets_received']],
            ['Assets with kilometers', $summary['assets_with_km']],
            ['Trucks matched', $summary['trucks_matched']],
            ['Trucks updated (new km)', $summary['trucks_updated']],
            ['KM trackings created', $summary['trackings_created']],
            ['Telemetry snapshots', $summary['snapshots_created'] ?? 0],
            ['Engine-hour trackings', $summary['engine_hour_trackings_created'] ?? 0],
            ['Fuel trackings', $summary['fuel_trackings_created'] ?? 0],
            ['Fuel events', $summary['fuel_events_detected'] ?? 0],
            ['Stops closed', $summary['stops_closed'] ?? 0],
            ['Theft incidents opened', $summary['theft_incidents_opened'] ?? 0],
            ['Assets skipped', $summary['assets_skipped']],
            ['Errors', count($summary['errors'])],
        ]);

        if (! empty($summary['errors'])) {
            $this->warn('Some assets failed to sync:');
            foreach ($summary['errors'] as $error) {
                $this->line('- '.json_encode($error));
            }
        }

        $this->info('Fleeti sync completed.');
        return self::SUCCESS;
    }
}
