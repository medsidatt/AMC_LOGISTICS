<?php

namespace App\Imports;

use App\Models\Transporter;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Models\Driver;
use App\Models\Provider;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Custom snake-case formatter
HeadingRowFormatter::extend('snake', function($value) {
    return strtolower(preg_replace('/\s+/', '_', trim($value)));
});
HeadingRowFormatter::default('snake');

class TransportTrackingImport implements ToModel, WithHeadingRow, WithCalculatedFormulas
{
    // Helper to parse numbers
    private function parseDecimal($value) {
        return is_numeric($value) ? $value : null;
    }

    public function model(array $row)
    {
        // Find existing record by ID
        $tracking = TransportTracking::find($row['id']);
        if (!$tracking) {
            return null;
        }

        // Transporter
        $transporter = !empty($row['transporter'])
            ? Transporter::firstOrCreate(['name' => $row['transporter']])
            : null;

        // Truck
        $truck = !empty($row['truck_number'])
            ? Truck::firstOrCreate(
                ['matricule' => $row['truck_number']],
                ['transporter_id' => $transporter?->id]
            )
            : null;

        // Driver
        $driver = !empty($row['driver'])
            ? Driver::firstOrCreate(['name' => $row['driver']])
            : null;

        // Provider
        $provider = !empty($row['supplier'])
            ? Provider::where('name', $row['supplier'])->first()
            : null;

        // Dates
        $providerDate = is_numeric($row['departure_date'] ?? null)
            ? Date::excelToDateTimeObject($row['departure_date'])->format('Y-m-d')
            : $row['departure_date'] ?? null;

        $clientDate = is_numeric($row['deliverydate'] ?? null)
            ? Date::excelToDateTimeObject($row['deliverydate'])->format('Y-m-d')
            : $row['deliverydate'] ?? null;

        // Prepare new data
        $newData = [
            'truck_id'              => $truck?->id ?? $tracking->truck_id,
            'provider_id'           => $provider?->id ?? $tracking->provider_id,
            'driver_id'             => $driver?->id ?? $tracking->driver_id,
            'product'               => !empty($row['product_type']) ? str_replace('-', '/', $row['product_type']) : $tracking->product,
            'provider_date'         => $providerDate ?? $tracking->provider_date,
            'client_date'           => $clientDate ?? $tracking->client_date,
            'provider_net_weight'   => $this->parseDecimal($row['supplier_net_weight']) ?? $tracking->provider_net_weight,
            'provider_gross_weight' => $this->parseDecimal($row['supplier_gross_weight']) ?? $tracking->provider_gross_weight,
            'provider_tare_weight'  => $this->parseDecimal($row['suppliertare'] ?? $row['supplier_tare']) ?? $tracking->provider_tare_weight,
            'client_net_weight'     => $this->parseDecimal($row['client_net_weight']) ?? $tracking->client_net_weight,
            'client_gross_weight'   => $this->parseDecimal($row['client_gross_weight']) ?? $tracking->client_gross_weight,
            'client_tare_weight'    => $this->parseDecimal($row['client_tare']) ?? $tracking->client_tare_weight,
            // gap is auto-calculated in model boot(): client_net_weight - provider_net_weight
        ];

        // Update only if something changed
        if (!$this->hasChanges($tracking, $newData)) {
            return $tracking;
        }

        $tracking->update($newData);

        return $tracking;
    }

    // Check if any column changed
    protected function hasChanges($model, array $newData): bool
    {
        foreach ($newData as $key => $value) {
            if ($model->{$key} != $value) {
                return true;
            }
        }
        return false;
    }
}
