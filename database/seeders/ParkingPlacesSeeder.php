<?php

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Seeder;

/**
 * Seeds known truck-parking spots (no provider/client attached).
 *
 * Cluster identified via `php artisan locations:list`:
 *   - (14.6964, -16.7064) — truck 6071TTA1 parks here repeatedly for
 *     multi-day stretches (April 2026: 2 stops, ~3 days each), ~7 km south
 *     of the Diack quarry. Treated as fleet parking, not a provider site.
 */
class ParkingPlacesSeeder extends Seeder
{
    /**
     * @var array<int, array{
     *   code:string, name:string, latitude:float, longitude:float, radius_m:int,
     * }>
     */
    protected array $parkings = [
        [
            'code' => 'PARK-DIACK-SUD',
            'name' => 'Parking Diack Sud',
            'latitude' => 14.6964,
            'longitude' => -16.7064,
            'radius_m' => 250,
        ],
    ];

    public function run(): void
    {
        foreach ($this->parkings as $row) {
            Place::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'type' => Place::TYPE_PARKING,
                    'provider_id' => null,
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'radius_m' => $row['radius_m'],
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info(sprintf(
            'ParkingPlacesSeeder: %d parking geofence(s) upserted.',
            count($this->parkings),
        ));
    }
}
