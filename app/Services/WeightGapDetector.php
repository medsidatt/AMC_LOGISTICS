<?php

namespace App\Services;

use App\Models\TheftIncident;
use App\Models\TransportTracking;
use App\Models\TripSegment;
use App\Models\TruckStop;

/**
 * Escalates a transport weight discrepancy into a theft incident.
 *
 * Trigger: `transport_trackings.gap <= -threshold` (negative gap means the
 * client received less than the provider delivered — cargo lost en route).
 *
 * Severity is scaled by how much was lost:
 *  - loss >= 1000 kg → high
 *  - loss >=  500 kg → medium
 *  - loss <   500 kg → low
 *
 * Evidence links the matching trip segment (if any) and lists the unknown
 * stops that occurred during it.
 */
class WeightGapDetector
{
    public function __construct(private readonly TheftIncidentService $theftIncidentService)
    {
    }

    public function inspect(TransportTracking $tt): ?TheftIncident
    {
        $gap = $tt->gap;
        if ($gap === null) {
            return null;
        }

        $threshold = (float) config('maintenance.weight_gap_threshold_kg', 300);
        if ((float) $gap > -$threshold) {
            return null;  // not suspicious (or cargo arrived with extra weight, which is fine)
        }

        $lossKg = abs((float) $gap);

        $severity = match (true) {
            $lossKg >= 1000 => TheftIncident::SEVERITY_HIGH,
            $lossKg >= 500 => TheftIncident::SEVERITY_MEDIUM,
            default => TheftIncident::SEVERITY_LOW,
        };

        $segment = TripSegment::query()
            ->where('transport_tracking_id', $tt->id)
            ->first();

        $unknownStops = collect();
        if ($segment) {
            $unknownStops = TruckStop::query()
                ->where('truck_id', $segment->truck_id)
                ->whereNotNull('ended_at')
                ->where('classification', TruckStop::CLASS_UNKNOWN)
                ->where('started_at', '>=', $segment->started_at)
                ->where('started_at', '<=', $segment->ended_at)
                ->orderBy('started_at')
                ->get();
        }

        $detectedAt = $tt->client_date
            ? $tt->client_date->copy()->endOfDay()
            : ($tt->provider_date?->copy()->endOfDay() ?? now());

        return $this->theftIncidentService->open([
            'truck_id' => $tt->truck_id,
            'transport_tracking_id' => $tt->id,
            'trip_segment_id' => $segment?->id,
            'type' => TheftIncident::TYPE_WEIGHT_GAP,
            'severity' => $severity,
            'detected_at' => $detectedAt,
            'title' => sprintf(
                'Écart de poids %s kg sur la mission #%s',
                number_format(-$lossKg, 0, ',', ' '),
                $tt->reference ?? $tt->id
            ),
            'evidence' => [
                'dedup_key' => 'weight_gap:transport=' . $tt->id,
                'provider_net_weight' => $tt->provider_net_weight,
                'client_net_weight' => $tt->client_net_weight,
                'gap_kg' => (float) $gap,
                'loss_kg' => $lossKg,
                'unknown_stops' => $unknownStops
                    ->map(fn (TruckStop $s) => [
                        'id' => $s->id,
                        'started_at' => $s->started_at?->toIso8601String(),
                        'ended_at' => $s->ended_at?->toIso8601String(),
                        'duration_minutes' => (int) round(($s->duration_seconds ?? 0) / 60),
                        'latitude' => $s->latitude,
                        'longitude' => $s->longitude,
                    ])
                    ->values()
                    ->all(),
                'unknown_stop_count' => $unknownStops->count(),
            ],
        ]);
    }
}
