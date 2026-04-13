<?php

namespace App\Services;

use App\Models\FuelTracking;
use App\Models\Truck;
use App\Models\TruckTelemetrySnapshot;

class FuelTrackingService
{
    /**
     * Persist a fuel tracking row enriched with the snapshot's contextual fields
     * (GPS, engine hours, ignition state). Returns null if no fuel reading is
     * present in the telemetry bundle.
     */
    public function record(
        Truck $truck,
        array $telemetry,
        TruckTelemetrySnapshot $snapshot,
        string $source = 'fleeti'
    ): ?FuelTracking {
        $litres = $telemetry['fuel_litres'] ?? null;
        if ($litres === null || (float) $litres <= 0) {
            return null;
        }

        return FuelTracking::create([
            'truck_id' => $truck->id,
            'telemetry_snapshot_id' => $snapshot->id,
            'litres' => round((float) $litres, 2),
            'kilometers_at' => $telemetry['odometer_km'] ?? $truck->fresh()->total_kilometers,
            'engine_hours_at' => $telemetry['engine_hours'] ?? null,
            'latitude' => $telemetry['latitude'] ?? null,
            'longitude' => $telemetry['longitude'] ?? null,
            'ignition_on' => $telemetry['ignition_on'] ?? null,
            'source' => $source,
        ]);
    }
}
