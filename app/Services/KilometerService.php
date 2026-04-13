<?php

namespace App\Services;

use App\Models\KilometerTracking;
use App\Models\Truck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KilometerService
{
    public function __construct(
        private readonly MaintenanceStatusService $maintenanceStatusService
    ) {
    }

    public function addDistance(
        Truck $truck,
        float $distanceKm,
        Carbon $date,
        ?string $notes = null,
        string $source = 'manual',
        ?int $telemetrySnapshotId = null
    ): ?KilometerTracking {
        if ($distanceKm <= 0) {
            throw ValidationException::withMessages([
                'kilometers' => 'Distance must be greater than 0.',
            ]);
        }

        $tracking = KilometerTracking::create([
            'truck_id' => $truck->id,
            'telemetry_snapshot_id' => $telemetrySnapshotId,
            'kilometers' => round($distanceKm, 2),
            'date' => $date->toDateString(),
            'notes' => trim(($notes ? $notes.' | ' : '')."Source: {$source}"),
        ]);

        $truck->increment('total_kilometers', round($distanceKm, 2));
        $truck->refresh();
        $this->maintenanceStatusService->recalculateForTruck($truck);

        return $tracking;
    }

    public function applyExternalOdometerReading(
        Truck $truck,
        float $odometerKm,
        Carbon $date,
        string $source = 'fleeti',
        ?int $telemetrySnapshotId = null
    ): array {
        $currentTotal = (float) $truck->total_kilometers;
        $lastRawOdometer = (float) ($truck->fleeti_last_kilometers ?? 0);
        $threshold = (float) config('maintenance.odometer_reset_threshold_km', 50000);

        $deltaKm = round($odometerKm - $currentTotal, 2);
        $event = 'normal';

        if ($odometerKm < $currentTotal) {
            $dropFromRaw = $lastRawOdometer > 0 ? ($lastRawOdometer - $odometerKm) : 0;

            if ($lastRawOdometer > 0 && $odometerKm < $lastRawOdometer && $dropFromRaw >= $threshold) {
                // Odometer reset: preserve cumulative total and only add distance since reset.
                $deltaKm = round($odometerKm, 2);
                $event = 'odometer_reset_detected';
                Log::warning('Odometer reset detected for truck.', [
                    'truck_id' => $truck->id,
                    'matricule' => $truck->matricule,
                    'source' => $source,
                    'last_raw_odometer' => $lastRawOdometer,
                    'new_odometer' => $odometerKm,
                    'delta_applied' => $deltaKm,
                ]);
            } else {
                Log::warning('Suspicious odometer rollback rejected.', [
                    'truck_id' => $truck->id,
                    'matricule' => $truck->matricule,
                    'source' => $source,
                    'current_total_km' => $currentTotal,
                    'incoming_odometer_km' => $odometerKm,
                ]);

                throw ValidationException::withMessages([
                    'kilometers' => "Odometer rollback rejected: {$odometerKm} < {$currentTotal}.",
                ]);
            }
        }

        if ($deltaKm <= 0) {
            $truck->update([
                'fleeti_last_kilometers' => round($odometerKm, 2),
                'fleeti_last_synced_at' => $date,
            ]);

            return ['updated' => false, 'delta_km' => 0.0, 'event' => 'no_change'];
        }

        KilometerTracking::create([
            'truck_id' => $truck->id,
            'telemetry_snapshot_id' => $telemetrySnapshotId,
            'kilometers' => $deltaKm,
            'date' => $date->toDateString(),
            'notes' => "Synced from {$source}".($event !== 'normal' ? " ({$event})" : ''),
        ]);

        $truck->update([
            'total_kilometers' => round($currentTotal + $deltaKm, 2),
            'fleeti_last_kilometers' => round($odometerKm, 2),
            'fleeti_last_synced_at' => $date,
        ]);

        $this->maintenanceStatusService->recalculateForTruck($truck->fresh());

        return ['updated' => true, 'delta_km' => $deltaKm, 'event' => $event];
    }
}
