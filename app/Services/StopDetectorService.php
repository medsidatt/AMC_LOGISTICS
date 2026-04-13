<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\TruckStop;
use App\Models\TruckTelemetrySnapshot;
use Illuminate\Support\Carbon;

/**
 * Derives TruckStop rows from incoming telemetry snapshots.
 *
 * Called incrementally from FleetiSyncService after each snapshot is recorded:
 * the detector either opens a new stop, extends the currently-open one, or
 * closes it (flagging the stop as finished so classifiers and detectors can
 * act on it).
 *
 * Returned array always contains the stops that were CLOSED by this call
 * (callers need the closed stops to run classification + unauthorized-stop
 * detection).
 */
class StopDetectorService
{
    public function __construct()
    {
    }

    /**
     * Incorporate one new snapshot into the truck's stop timeline.
     *
     * @return TruckStop[]  Stops that transitioned to "closed" during this call.
     */
    public function extendForTruck(Truck $truck, TruckTelemetrySnapshot $snapshot): array
    {
        // Snapshots without GPS cannot anchor a stop.
        if ($snapshot->latitude === null || $snapshot->longitude === null) {
            return [];
        }

        $isStationary = $this->isStationary($snapshot);
        $isMoving = $this->isMoving($snapshot);

        $openStop = TruckStop::query()
            ->where('truck_id', $truck->id)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();

        $closedStops = [];

        if ($openStop) {
            if ($isStationary) {
                $this->extendOpenStop($openStop, $snapshot);
            } elseif ($isMoving) {
                $closed = $this->closeStop($openStop, $snapshot);
                if ($closed) {
                    $closedStops[] = $closed;
                }
            }
            // Otherwise: ambiguous (idle but unknown status) — leave the stop alone.

            return $closedStops;
        }

        // No open stop. Only open a new one if the truck is actually stationary.
        if ($isStationary) {
            $this->openNewStop($truck, $snapshot);
        }

        return $closedStops;
    }

    private function isStationary(TruckTelemetrySnapshot $s): bool
    {
        $speedStopped = $s->speed_kmh !== null && (float) $s->speed_kmh < 1.0;
        $parked = $s->movement_status === 'parked';
        $ignitionOff = $s->ignition_on === false;

        // Any of (parked OR ignition off) counts as stopped; speed must agree
        // if it's present to avoid tagging a snapshot with a speed reading of 30 as stopped.
        if ($speedStopped || $s->speed_kmh === null) {
            return $parked || $ignitionOff;
        }

        return false;
    }

    private function isMoving(TruckTelemetrySnapshot $s): bool
    {
        $hasSpeed = $s->speed_kmh !== null && (float) $s->speed_kmh >= 3.0;
        $moving = $s->movement_status === 'moving';

        return $hasSpeed || $moving;
    }

    private function openNewStop(Truck $truck, TruckTelemetrySnapshot $snapshot): TruckStop
    {
        return TruckStop::create([
            'truck_id' => $truck->id,
            'start_snapshot_id' => $snapshot->id,
            'started_at' => $snapshot->recorded_at ?? $snapshot->synced_at ?? now(),
            'ended_at' => null,
            'duration_seconds' => null,
            'latitude' => $snapshot->latitude,
            'longitude' => $snapshot->longitude,
            'ignition_was_off' => $snapshot->ignition_on === false ? true
                : ($snapshot->ignition_on === true ? false : null),
            'fuel_litres_at_start' => $snapshot->fuel_litres,
        ]);
    }

    private function extendOpenStop(TruckStop $stop, TruckTelemetrySnapshot $snapshot): void
    {
        $updates = [
            'end_snapshot_id' => $snapshot->id,
            'fuel_litres_at_end' => $snapshot->fuel_litres,
        ];

        if ($snapshot->ignition_on === false) {
            $updates['ignition_was_off'] = true;
        }

        $stop->update($updates);
    }

    private function closeStop(TruckStop $stop, TruckTelemetrySnapshot $snapshot): ?TruckStop
    {
        $endedAt = $snapshot->recorded_at ?? $snapshot->synced_at ?? Carbon::now();
        $startedAt = $stop->started_at instanceof Carbon
            ? $stop->started_at
            : Carbon::parse($stop->started_at);

        $durationSeconds = max(0, $endedAt->diffInSeconds($startedAt, true));

        $minDuration = (int) config('maintenance.stops_min_duration_seconds', 300);

        // Too short to care about — delete the row entirely so we don't clutter
        // the table with blink stops at traffic lights.
        if ($durationSeconds < $minDuration) {
            $stop->delete();
            return null;
        }

        $stop->update([
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
            'end_snapshot_id' => $stop->end_snapshot_id ?? $snapshot->id,
            'fuel_litres_at_end' => $stop->fuel_litres_at_end ?? $snapshot->fuel_litres,
        ]);

        return $stop->fresh();
    }
}
