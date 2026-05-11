<?php

namespace Database\Seeders;

use App\Models\Driver;
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

    }
}
