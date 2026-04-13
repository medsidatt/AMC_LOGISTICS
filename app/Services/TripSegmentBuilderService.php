<?php

namespace App\Services;

use App\Models\TransportTracking;
use App\Models\TripSegment;
use App\Models\Truck;
use App\Models\TruckTelemetrySnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Builds TripSegment rows that correspond to transport missions, so the
 * theft-detection layer can ask questions like "for transport AMC00123,
 * which snapshots and stops belong to that mission?".
 *
 * A segment is defined by a time window on the truck's timeline. For a
 * transport_tracking we use [provider_date − 6h, client_date + 6h] as a
 * buffer window, then trim it to the actual telemetry we find inside.
 *
 * This service is deliberately idempotent: calling buildForTransport()
 * twice in a row will update the same row, not duplicate it.
 */
class TripSegmentBuilderService
{
    private const WINDOW_BUFFER_HOURS = 6;

    public function __construct(
        private readonly ?GeoService $geoService = null,
        private readonly ?RouteDeviationDetector $routeDeviationDetector = null
    ) {
    }

    /**
     * Build or refresh the trip segment for a given transport tracking.
     *
     * Safe to call on partially-populated transports: if there's no truck
     * or no dates yet, the method bails out and returns null.
     */
    public function buildForTransport(TransportTracking $tt): ?TripSegment
    {
        if ($tt->truck_id === null) {
            return null;
        }

        $providerAt = $tt->provider_date
            ? Carbon::parse($tt->provider_date)->startOfDay()
            : null;
        $clientAt = $tt->client_date
            ? Carbon::parse($tt->client_date)->endOfDay()
            : null;

        if ($providerAt === null && $clientAt === null) {
            return null;
        }

        $windowStart = ($providerAt ?? $clientAt)->copy()->subHours(self::WINDOW_BUFFER_HOURS);
        $windowEnd = ($clientAt ?? $providerAt)->copy()->addHours(self::WINDOW_BUFFER_HOURS);

        // Ensure ordering is sane when only one date is supplied.
        if ($windowEnd->lessThan($windowStart)) {
            [$windowStart, $windowEnd] = [$windowEnd, $windowStart];
        }

        // Snapshots for this truck inside the window (ordered chronologically)
        $snapshots = TruckTelemetrySnapshot::query()
            ->where('truck_id', $tt->truck_id)
            ->whereBetween('recorded_at', [$windowStart, $windowEnd])
            ->orderBy('recorded_at')
            ->get();

        $firstSnapshot = $snapshots->first();
        $lastSnapshot = $snapshots->last();

        // Prefer telemetry-anchored dates; fall back to the transport dates.
        $startedAt = $firstSnapshot?->recorded_at ?? $windowStart;
        $endedAt = $lastSnapshot?->recorded_at ?? $windowEnd;

        // Odometer boundaries: prefer the transport's explicit start/end km
        // (weighbridge-verified), fall back to telemetry.
        $startOdo = $tt->start_km !== null
            ? (float) $tt->start_km
            : ($firstSnapshot?->odometer_km !== null ? (float) $firstSnapshot->odometer_km : null);
        $endOdo = $tt->end_km !== null
            ? (float) $tt->end_km
            : ($lastSnapshot?->odometer_km !== null ? (float) $lastSnapshot->odometer_km : null);

        $distanceKm = ($startOdo !== null && $endOdo !== null && $endOdo >= $startOdo)
            ? round($endOdo - $startOdo, 2)
            : null;

        // Fuel consumed = fuel_at_start - fuel_at_end, if both known and the
        // end level is lower than the start (excludes the case where a refill
        // happened mid-trip).
        $fuelStart = $firstSnapshot?->fuel_litres;
        $fuelEnd = $lastSnapshot?->fuel_litres;
        $fuelConsumed = ($fuelStart !== null && $fuelEnd !== null && (float) $fuelStart > (float) $fuelEnd)
            ? round((float) $fuelStart - (float) $fuelEnd, 2)
            : null;

        // Stops that fall inside the window (must be closed — open stops don't
        // count toward a finished trip segment yet).
        $stops = \App\Models\TruckStop::query()
            ->where('truck_id', $tt->truck_id)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$startedAt, $endedAt])
            ->get();

        $unknownStopCount = $stops
            ->where('classification', \App\Models\TruckStop::CLASS_UNKNOWN)
            ->count();

        // Classify origin/destination from the trail's first and last GPS fix.
        // Uses GeoService::nearestCoveringPlace when available (the geo service
        // is nullable so Phase A's simpler constructor still works).
        $originPlaceId = null;
        $destinationPlaceId = null;
        if ($this->geoService !== null) {
            if ($firstSnapshot && $firstSnapshot->latitude !== null && $firstSnapshot->longitude !== null) {
                $originPlaceId = $this->geoService
                    ->nearestCoveringPlace((float) $firstSnapshot->latitude, (float) $firstSnapshot->longitude)
                    ?->id;
            }
            if ($lastSnapshot && $lastSnapshot->latitude !== null && $lastSnapshot->longitude !== null) {
                $destinationPlaceId = $this->geoService
                    ->nearestCoveringPlace((float) $lastSnapshot->latitude, (float) $lastSnapshot->longitude)
                    ?->id;
            }
        }

        $segment = TripSegment::updateOrCreate(
            ['transport_tracking_id' => $tt->id],
            [
                'truck_id' => $tt->truck_id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'start_snapshot_id' => $firstSnapshot?->id,
                'end_snapshot_id' => $lastSnapshot?->id,
                'start_odometer_km' => $startOdo,
                'end_odometer_km' => $endOdo,
                'distance_km' => $distanceKm,
                'fuel_consumed_litres' => $fuelConsumed,
                'stop_count' => $stops->count(),
                'unknown_stop_count' => $unknownStopCount,
                'origin_place_id' => $originPlaceId,
                'destination_place_id' => $destinationPlaceId,
            ]
        );

        // Route deviation check — only meaningful when both places are set.
        if ($this->routeDeviationDetector !== null
            && $originPlaceId !== null
            && $destinationPlaceId !== null) {
            try {
                $this->routeDeviationDetector->inspect($segment);
            } catch (\Throwable $e) {
                Log::warning('Route deviation check failed.', [
                    'trip_segment_id' => $segment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $segment;
    }

    /**
     * Build unlinked segments (truck was moving but no transport tracking).
     * Used by the nightly rebuild command and the off-hours detector.
     *
     * Implementation note: this is intentionally minimal for Phase A. It
     * creates one unlinked segment per [from..to] window when the window
     * contains any snapshot with speed_kmh >= work-hours moving threshold.
     * Phase C can upgrade this to real ignition-off→on clustering.
     */
    public function buildUnlinkedSegments(Truck $truck, Carbon $from, Carbon $to): ?TripSegment
    {
        $snapshots = TruckTelemetrySnapshot::query()
            ->where('truck_id', $truck->id)
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->get();

        if ($snapshots->isEmpty()) {
            return null;
        }

        $firstSnapshot = $snapshots->first();
        $lastSnapshot = $snapshots->last();

        return TripSegment::create([
            'truck_id' => $truck->id,
            'transport_tracking_id' => null,
            'started_at' => $firstSnapshot->recorded_at ?? $from,
            'ended_at' => $lastSnapshot->recorded_at ?? $to,
            'start_snapshot_id' => $firstSnapshot->id,
            'end_snapshot_id' => $lastSnapshot->id,
            'start_odometer_km' => $firstSnapshot->odometer_km,
            'end_odometer_km' => $lastSnapshot->odometer_km,
            'distance_km' => ($firstSnapshot->odometer_km !== null && $lastSnapshot->odometer_km !== null)
                ? round((float) $lastSnapshot->odometer_km - (float) $firstSnapshot->odometer_km, 2)
                : null,
            'fuel_consumed_litres' => null,
            'stop_count' => 0,
            'unknown_stop_count' => 0,
        ]);
    }
}
