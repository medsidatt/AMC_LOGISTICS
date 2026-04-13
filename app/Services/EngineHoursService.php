<?php

namespace App\Services;

use App\Models\EngineHourTracking;
use App\Models\Truck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class EngineHoursService
{
    /**
     * Apply an external engine-hour reading (e.g. from Fleeti).
     * Mirrors KilometerService::applyExternalOdometerReading but for engine hours.
     *
     * - Compares the incoming absolute reading against truck.fleeti_last_engine_hours
     *   (which is already updated by TelemetrySnapshotService BEFORE this is called,
     *   so we read the PREVIOUS value from the trucks' original attribute snapshot).
     * - Only creates an EngineHourTracking row when delta > 0.
     * - Handles hour-meter rollover (rare but possible on replaced ECUs).
     *
     * @return array{updated: bool, delta_hours: float, event: string}
     */
    public function applyExternalReading(
        Truck $truck,
        float $hours,
        Carbon $date,
        string $source = 'fleeti',
        ?int $telemetrySnapshotId = null
    ): array {
        // Read the PREVIOUS cached hours (before the current sync updated the cache).
        // `getOriginal()` returns the model's value BEFORE any unsaved changes. Since
        // TelemetrySnapshotService already persisted the new value, we query the DB
        // for the previous row directly.
        $previousHours = (float) ($this->findPreviousHours($truck) ?? 0);
        $threshold = (float) config('maintenance.engine_hours_reset_threshold', 1000);

        $delta = round($hours - $previousHours, 2);
        $event = 'normal';

        if ($hours < $previousHours) {
            $drop = $previousHours - $hours;
            if ($drop >= $threshold) {
                // Engine-hour meter reset detected — only add the reading itself as delta.
                $delta = round($hours, 2);
                $event = 'engine_hours_reset_detected';
                Log::warning('Engine hours meter reset detected for truck.', [
                    'truck_id' => $truck->id,
                    'matricule' => $truck->matricule,
                    'source' => $source,
                    'previous_hours' => $previousHours,
                    'new_hours' => $hours,
                    'delta_applied' => $delta,
                ]);
            } else {
                Log::warning('Suspicious engine hours rollback rejected.', [
                    'truck_id' => $truck->id,
                    'matricule' => $truck->matricule,
                    'source' => $source,
                    'previous_hours' => $previousHours,
                    'incoming_hours' => $hours,
                ]);
                return ['updated' => false, 'delta_hours' => 0.0, 'event' => 'rollback_rejected'];
            }
        }

        if ($delta <= 0) {
            return ['updated' => false, 'delta_hours' => 0.0, 'event' => 'no_change'];
        }

        EngineHourTracking::create([
            'truck_id' => $truck->id,
            'telemetry_snapshot_id' => $telemetrySnapshotId,
            'hours_delta' => $delta,
            'date' => $date->toDateString(),
            'source' => $source,
            'notes' => $event !== 'normal' ? $event : null,
        ]);

        return ['updated' => true, 'delta_hours' => $delta, 'event' => $event];
    }

    /**
     * Find the previous engine-hours reading from the snapshots table,
     * excluding the current truck's freshest cached value (which may already
     * have been updated this sync).
     */
    private function findPreviousHours(Truck $truck): ?float
    {
        // Second-to-latest snapshot (since the latest is the current sync)
        $previous = $truck->telemetrySnapshots()
            ->whereNotNull('engine_hours')
            ->orderByDesc('recorded_at')
            ->skip(1)
            ->first();

        return $previous ? (float) $previous->engine_hours : null;
    }
}
