<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\TruckTelemetrySnapshot;
use Illuminate\Support\Carbon;

class TelemetrySnapshotService
{
    /**
     * Record a telemetry snapshot and update the trucks last-value cache.
     *
     * @param  array{
     *   recorded_at?: ?Carbon,
     *   odometer_km?: ?float,
     *   engine_hours?: ?float,
     *   fuel_litres?: ?float,
     *   speed_kmh?: ?float,
     *   latitude?: ?float,
     *   longitude?: ?float,
     *   heading_deg?: ?float,
     *   gps_accuracy_m?: ?float,
     *   ignition_on?: ?bool,
     *   movement_status?: ?string,
     *   battery_voltage?: ?float,
     *   signal_strength?: ?int,
     *   device_last_seen_at?: ?Carbon,
     * }  $telemetry
     */
    public function record(
        Truck $truck,
        array $telemetry,
        ?array $rawPayload = null,
        string $source = 'fleeti'
    ): TruckTelemetrySnapshot {
        $now = now();

        $snapshot = TruckTelemetrySnapshot::create([
            'truck_id' => $truck->id,
            'recorded_at' => $telemetry['recorded_at'] ?? $now,
            'synced_at' => $now,
            'source' => $source,
            'odometer_km' => $telemetry['odometer_km'] ?? null,
            'engine_hours' => $telemetry['engine_hours'] ?? null,
            'fuel_litres' => $telemetry['fuel_litres'] ?? null,
            'speed_kmh' => $telemetry['speed_kmh'] ?? null,
            'latitude' => $telemetry['latitude'] ?? null,
            'longitude' => $telemetry['longitude'] ?? null,
            'heading_deg' => $telemetry['heading_deg'] ?? null,
            'gps_accuracy_m' => $telemetry['gps_accuracy_m'] ?? null,
            'ignition_on' => $telemetry['ignition_on'] ?? null,
            'movement_status' => $telemetry['movement_status'] ?? null,
            'battery_voltage' => $telemetry['battery_voltage'] ?? null,
            'signal_strength' => $telemetry['signal_strength'] ?? null,
            'device_last_seen_at' => $telemetry['device_last_seen_at'] ?? null,
            'raw_payload' => $rawPayload,
            'created_at' => $now,
        ]);

        $this->updateTruckCache($truck, $telemetry);

        return $snapshot;
    }

    /**
     * Update the trucks table last-value cache with every non-null telemetry field.
     * Fields that are null in the incoming payload are left untouched.
     */
    private function updateTruckCache(Truck $truck, array $telemetry): void
    {
        $updates = [];

        if (array_key_exists('engine_hours', $telemetry) && $telemetry['engine_hours'] !== null) {
            $updates['fleeti_last_engine_hours'] = $telemetry['engine_hours'];
        }
        if (array_key_exists('speed_kmh', $telemetry) && $telemetry['speed_kmh'] !== null) {
            $updates['fleeti_last_speed_kmh'] = $telemetry['speed_kmh'];
        }
        if (array_key_exists('latitude', $telemetry) && $telemetry['latitude'] !== null) {
            $updates['fleeti_last_latitude'] = $telemetry['latitude'];
        }
        if (array_key_exists('longitude', $telemetry) && $telemetry['longitude'] !== null) {
            $updates['fleeti_last_longitude'] = $telemetry['longitude'];
        }
        if (array_key_exists('heading_deg', $telemetry) && $telemetry['heading_deg'] !== null) {
            $updates['fleeti_last_heading_deg'] = $telemetry['heading_deg'];
        }
        if (array_key_exists('ignition_on', $telemetry) && $telemetry['ignition_on'] !== null) {
            $updates['fleeti_last_ignition_on'] = $telemetry['ignition_on'];
        }
        if (array_key_exists('movement_status', $telemetry) && $telemetry['movement_status'] !== null) {
            $updates['fleeti_last_movement_status'] = $telemetry['movement_status'];
        }
        if (array_key_exists('battery_voltage', $telemetry) && $telemetry['battery_voltage'] !== null) {
            $updates['fleeti_last_battery_voltage'] = $telemetry['battery_voltage'];
        }
        if (array_key_exists('signal_strength', $telemetry) && $telemetry['signal_strength'] !== null) {
            $updates['fleeti_last_signal_strength'] = $telemetry['signal_strength'];
        }
        if (array_key_exists('device_last_seen_at', $telemetry) && $telemetry['device_last_seen_at'] !== null) {
            $updates['fleeti_device_last_seen_at'] = $telemetry['device_last_seen_at'];
        }

        if (! empty($updates)) {
            $truck->update($updates);
        }
    }
}
