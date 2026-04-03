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
            ['Trucks updated', $summary['trucks_updated']],
            ['Trackings created', $summary['trackings_created']],
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
