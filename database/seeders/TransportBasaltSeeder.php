<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Provider;
use App\Models\Transporter;
use App\Models\Truck;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TransportBasaltSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $transporter = Transporter::firstOrCreate(
            ['name' => 'AMC Travaux SN SARL'],
            [
                'address' => '',
                'phone' => '',
            ],
        );

        $trucks = [
            [
                'matricule' => 'AA627FP',
                'transporter_id' => $transporter->id,
            ],
            [
                'matricule' => 'AA055VH',
                'transporter_id' => $transporter->id,
            ],
            [
                'matricule' => 'AA284PW',
                'transporter_id' => $transporter->id,
            ],
            [
                'matricule' => 'AA536XK',
                'transporter_id' => $transporter->id,
            ],
            [
                'matricule' => 'AA726PZ',
                'transporter_id' => $transporter->id,
            ],
            [
                'matricule' => 'AA290DW',
                'transporter_id' => $transporter->id,
            ],

        ];

        $drivers = [
            [
                'name' => 'Brahama GUEYE',
                'address' => '',
                'phone' => '',
            ],
            [
                'name' => 'Cheikh MBAYE',
                'address' => '',
                'phone' => '',
            ],
            [
                'name' => 'Mouhamadoul BOYE',
                'address' => '',
                'phone' => '',
            ],
            [
                'name' => 'More GUEYE',
                'address' => '',
                'phone' => '',
            ],
            [
                'name' => 'Mame G THIAM',
                'address' => '',
                'phone' => '',
            ],
        ];

        $providers = [
            [
                'name' => 'CSE GRANULATS',
                'address' => '',
                'phone' => '',
            ],
            [
                'name' => 'CO.GE.CA',
                'address' => '96 Rufisque, Dakar',
                'phone' => '839 87 27 / 836 33 88',
            ],
        ];

        foreach ($drivers as $driver) {
            Driver::firstOrCreate(
                ['name' => $driver['name']],
                [
                    'address' => $driver['address'],
                    'phone' => $driver['phone'],
                ]
            );
        }

        foreach ($trucks as $truck) {
            Truck::firstOrCreate(
                ['matricule' => $truck['matricule']],
                [
                    'transporter_id' => $truck['transporter_id'],
                ]
            );
        }

        foreach ($providers as $provider) {
            Provider::firstOrCreate(
                ['name' => $provider['name']],
                [
                    'address' => $provider['address'],
                    'phone' => $provider['phone'],
                ]
            );
        }

    }
}
