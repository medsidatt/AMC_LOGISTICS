<?php

namespace App\Services;

use App\Models\Place;
use App\Models\TheftIncident;
use App\Models\TripSegment;
use App\Models\Truck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Flag freight-shaped trips that have no transport_tracking ticket.
 *
 * A "freight trip" is a 3-segment sequence for the same truck:
 *   1.  parking -> provider_site   (empty truck heading out to load)
 *   2.  provider_site -> client_site   (loaded leg)
 *   3.  client_site -> parking   (empty truck returning)
 *
 * If any of the three segments is missing a transport_tracking_id we
 * consider the whole loop "untracked" — the truck physically performed
 * a delivery but no bon de transport was recorded.
 */
class UntrackedTripDetector
{
    public function __construct(
        private TheftIncidentService $incidents,
        private FreightLoopService $loops,
    ) {}

    /**
     * Scan trip segments ended within the look-back window and flag any
     * untracked freight loops. Returns the list of incidents opened.
     */
    public function runOverWindow(int $days): array
    {
        $cutoff = Carbon::now()->subDays($days);
        $opened = [];

        // Process one truck at a time so the segment ordering is clean.
        $truckIds = TripSegment::query()
            ->where('ended_at', '>=', $cutoff)
            ->distinct()
            ->pluck('truck_id')
            ->all();

        foreach ($truckIds as $truckId) {
            $opened = array_merge($opened, $this->scanTruck((int) $truckId, $cutoff));
        }

        // Auto-close any pending incidents whose trip now has a ticket.
        $this->closeNowLinkedIncidents();

        return $opened;
    }

    private function scanTruck(int $truckId, Carbon $cutoff): array
    {
        $segments = TripSegment::query()
            ->with(['originPlace', 'destinationPlace'])
            ->where('truck_id', $truckId)
            ->where('ended_at', '>=', $cutoff)
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        if ($segments->count() < 3) {
            return [];
        }

        $opened = [];
        $truck = Truck::find($truckId);

        // Sliding window of 3 consecutive segments.
        for ($i = 0; $i + 2 < $segments->count(); $i++) {
            $a = $segments[$i];
            $b = $segments[$i + 1];
            $c = $segments[$i + 2];

            if (! $this->loops->matchesFreightLoop($a, $b, $c)) {
                continue;
            }

            // Strict (a): flag if any of the three legs has no ticket.
            $linked = $a->transport_tracking_id || $b->transport_tracking_id || $c->transport_tracking_id;
            if ($linked) {
                continue;
            }

            $opened[] = $this->openIncident($truck, $a, $b, $c);

            // Advance past this loop to avoid overlapping detections.
            $i += 2;
        }

        return $opened;
    }

    private function openIncident(?Truck $truck, TripSegment $a, TripSegment $b, TripSegment $c): TheftIncident
    {
        $evidence = [
            'truck_matricule' => $truck?->matricule,
            'parking_departure_at' => $a->started_at?->toIso8601String(),
            'provider_arrival_at' => $a->ended_at?->toIso8601String(),
            'provider_departure_at' => $b->started_at?->toIso8601String(),
            'client_arrival_at' => $b->ended_at?->toIso8601String(),
            'client_departure_at' => $c->started_at?->toIso8601String(),
            'parking_arrival_at' => $c->ended_at?->toIso8601String(),
            'parking_place' => ['id' => $a->originPlace->id, 'label' => $a->originPlace->label ?? $a->originPlace->name ?? '—'],
            'provider_place' => ['id' => $a->destinationPlace->id, 'label' => $a->destinationPlace->label ?? $a->destinationPlace->name ?? '—'],
            'client_place' => ['id' => $b->destinationPlace->id, 'label' => $b->destinationPlace->label ?? $b->destinationPlace->name ?? '—'],
            'distance_km' => round(
                (float) ($a->distance_km ?? 0) + (float) ($b->distance_km ?? 0) + (float) ($c->distance_km ?? 0),
                2,
            ),
            'segment_ids' => [$a->id, $b->id, $c->id],
        ];

        return $this->incidents->open([
            'truck_id' => $a->truck_id,
            'trip_segment_id' => $b->id,
            'type' => TheftIncident::TYPE_UNTRACKED_TRIP,
            'severity' => TheftIncident::SEVERITY_HIGH,
            'detected_at' => $c->ended_at ?? Carbon::now(),
            'latitude' => $b->destinationPlace->latitude ?? null,
            'longitude' => $b->destinationPlace->longitude ?? null,
            'evidence' => $evidence,
        ]);
    }

    /**
     * For every pending untracked_trip incident, check if the loaded-leg
     * trip segment now has a transport_tracking_id. If so the ticket
     * arrived late — auto-close the incident.
     */
    private function closeNowLinkedIncidents(): void
    {
        $pending = TheftIncident::query()
            ->where('type', TheftIncident::TYPE_UNTRACKED_TRIP)
            ->where('status', TheftIncident::STATUS_PENDING)
            ->whereNotNull('trip_segment_id')
            ->get();

        if ($pending->isEmpty()) return;

        $now = Carbon::now();

        foreach ($pending as $incident) {
            $segmentIds = data_get($incident->evidence, 'segment_ids', [$incident->trip_segment_id]);
            $linked = TripSegment::whereIn('id', $segmentIds)
                ->whereNotNull('transport_tracking_id')
                ->exists();

            if ($linked) {
                $incident->forceFill([
                    'status' => TheftIncident::STATUS_DISMISSED,
                    'reviewed_at' => $now,
                    'review_notes' => 'Bon de transport saisi après détection — clôturé automatiquement.',
                ])->save();
            }
        }
    }
}
