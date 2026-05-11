<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\Provider;
use Illuminate\Database\Seeder;

/**
 * Seeds the project's single client (POLY CHANGDA) and its two operating
 * bases on either side of the Senegal River at Rosso.
 *
 *   - POLY-SN base (16.5011, -15.7928) — observed directly: 3 distinct trucks
 *     unloaded here in April 2026, ~1 h dwell each. South bank of the river.
 *   - POLY-MR base (16.5180, -15.8100) — Mauritanian side of Rosso, just
 *     across the river. Not visible in the 30-day stop window (trucks didn't
 *     cross during the sample), so coordinates are taken from the public
 *     Rosso-Mauritania port-of-entry. Adjust if your real base differs.
 */
class ProviderClientSeeder extends Seeder
{
    /**
     * @var array<int, array{
     *   code:string, name:string, latitude:float, longitude:float, radius_m:int,
     * }>
     */
    protected array $bases = [
        [
            'code' => 'POLY-SN',
            'name' => 'Poly Changda — Base Rosso (Sénégal)',
            'latitude' => 16.5011,
            'longitude' => -15.7928,
            'radius_m' => 300,
        ],
        [
            'code' => 'POLY-MR',
            'name' => 'Poly Changda — Base Rosso (Mauritanie)',
            'latitude' => 16.5180,
            'longitude' => -15.8100,
            'radius_m' => 300,
        ],
    ];

    public function run(): void
    {
        $provider = Provider::updateOrCreate(
            ['name' => 'POLY'],
            [
                'address' => 'Chantier route Rosso–Saint-Louis, Sénégal/Mauritanie',
                'phone' => '',
            ],
        );

        foreach ($this->bases as $base) {
            Place::updateOrCreate(
                ['code' => $base['code']],
                [
                    'name' => $base['name'],
                    'type' => Place::TYPE_CLIENT_SITE,
                    'provider_id' => $provider->id,
                    'latitude' => $base['latitude'],
                    'longitude' => $base['longitude'],
                    'radius_m' => $base['radius_m'],
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info(sprintf(
            'ProviderClientSeeder: POLY CHANGDA + %d client_site geofences upserted.',
            count($this->bases),
        ));
    }
}
