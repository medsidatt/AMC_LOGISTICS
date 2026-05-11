<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\Provider;
use Illuminate\Database\Seeder;

/**
 * Seeds the two basalt providers (Senegalese quarries) and their geofences.
 *
 * Coordinates were derived from `php artisan locations:list` over April 2026:
 *   - Cluster A (14.7670, -16.7370) — 5 distinct trucks parked here for days
 *     at a time. The dominant hub. Mapped to CSE GRANULATS.
 *   - Cluster B (14.7610, -16.6863) — 1 truck, 3 stops, 2-3 day parkings,
 *     ~5 km east of cluster A. Mapped to CO.GE.CA.
 *
 * Both quarries sit in the Pout / Diack basalt zone, region of Thiès (SN).
 * Provider names match the originals from TransportBasaltSeeder.
 */
class ProviderCarrierSeeder extends Seeder
{
    /**
     * @var array<int, array{
     *   name:string, address:string, phone:string,
     *   place_code:string, place_name:string,
     *   latitude:float, longitude:float, radius_m:int,
     * }>
     */
    protected array $providers = [
        [
            'name' => 'CSE',
            'address' => 'Carrière de Diack, Région de Thiès, Sénégal',
            'phone' => '',
            'place_code' => 'CSE-DIACK',
            'place_name' => 'CSE Granulats — Carrière de Diack',
            'latitude' => 14.7670,
            'longitude' => -16.7370,
            'radius_m' => 400,
        ],
        [
            'name' => 'COGECA',
            'address' => '96 Rufisque, Dakar, Sénégal',
            'phone' => '839 87 27 / 836 33 88',
            'place_code' => 'COGECA-POUT',
            'place_name' => 'CO.GE.CA — Carrière de Pout',
            'latitude' => 14.7610,
            'longitude' => -16.6863,
            'radius_m' => 400,
        ],
    ];

    public function run(): void
    {
        foreach ($this->providers as $row) {
            $provider = Provider::updateOrCreate(
                ['name' => $row['name']],
                [
                    'address' => $row['address'],
                    'phone' => $row['phone'],
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                ],
            );

            Place::updateOrCreate(
                ['code' => $row['place_code']],
                [
                    'name' => $row['place_name'],
                    'type' => Place::TYPE_PROVIDER_SITE,
                    'provider_id' => $provider->id,
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'radius_m' => $row['radius_m'],
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info(sprintf(
            'ProviderCarrierSeeder: %d providers + provider_site geofences upserted.',
            count($this->providers),
        ));
    }
}
