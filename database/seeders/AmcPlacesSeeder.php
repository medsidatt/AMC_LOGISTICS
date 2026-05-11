<?php

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seeds Places with VALIDATED data only.
 *
 * Strategy: do not invent coordinates. We:
 *   1. Drop any prior placeholder rows seeded with fake coords (codes
 *      COGECA-*, HSE-*, POLY-* and the legacy is_active=false placeholders).
 *   2. Pull whatever real geofences Fleeti has via `fleeti:import-geofences`
 *      (idempotent; produces rows keyed `FLEETI-{id}`).
 *
 * Anything else (specific carrière / client coordinates) must be entered
 * manually at /logistics/places — we have no source of truth for those yet.
 */
class AmcPlacesSeeder extends Seeder
{
    /** Codes from the previous placeholder seeder — removed because their coords were guesses. */
    private const STALE_PLACEHOLDER_CODES = [
        'COGECA-SN', 'COGECA-MR',
        'HSE-SN', 'HSE-MR',
        'POLY-SN', 'POLY-MR',
    ];

    public function run(): void
    {
        $removed = Place::whereIn('code', self::STALE_PLACEHOLDER_CODES)->delete();
        if ($removed > 0) {
            $this->command?->warn("Removed {$removed} stale placeholder Place(s).");
        }

        $this->command?->info('Importing real geofences from Fleeti...');
        $exit = Artisan::call('fleeti:import-geofences', [], $this->command?->getOutput());

        if ($exit !== Command::SUCCESS) {
            $this->command?->warn(
                'fleeti:import-geofences did not complete successfully. '
                . 'Check FLEETI_API_KEY and re-run `php artisan fleeti:import-geofences` manually.'
            );
        }

        $count = Place::count();
        $this->command?->info("Places now in DB: {$count}.");
        $this->command?->line(
            'Next: open /logistics/places to set the correct `type` (provider_site / client_site) '
            . 'on each imported geofence, and to add any sites Fleeti does not have.'
        );
    }
}
