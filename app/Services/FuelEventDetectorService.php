<?php

namespace App\Services;

use App\Models\FuelEvent;
use App\Models\LogisticsAlert;
use App\Models\TheftIncident;
use App\Models\Truck;
use App\Models\TruckTelemetrySnapshot;

class FuelEventDetectorService
{
    public function __construct(
        private readonly ?TheftIncidentService $theftIncidentService = null
    ) {
    }

    /**
     * Analyze the most recent snapshot against the previous one and, if the
     * fuel delta crosses configured thresholds, persist a FuelEvent row.
     * Theft-suspected events also raise a LogisticsAlert and (when the
     * theft-incident service is available) open a theft_incident row.
     */
    public function analyze(Truck $truck, TruckTelemetrySnapshot $snapshotAfter): ?FuelEvent
    {
        $litresAfter = $snapshotAfter->fuel_litres;
        if ($litresAfter === null) {
            return null;
        }

        $snapshotBefore = $truck->telemetrySnapshots()
            ->whereNotNull('fuel_litres')
            ->where('id', '<', $snapshotAfter->id)
            ->orderByDesc('id')
            ->first();

        if (! $snapshotBefore) {
            return null;
        }

        $litresBefore = (float) $snapshotBefore->fuel_litres;
        $delta = round($litresAfter - $litresBefore, 2);

        $refillThreshold = (float) config('maintenance.fuel_refill_threshold_litres', 30);
        $dropThreshold = (float) config('maintenance.fuel_drop_threshold_litres', 15);

        $eventType = null;
        if ($delta >= $refillThreshold) {
            $eventType = FuelEvent::TYPE_REFILL;
        } elseif ($delta <= -$dropThreshold) {
            $ignition = $snapshotAfter->ignition_on;
            $movement = $snapshotAfter->movement_status;
            $isParked = $ignition === false || $movement === 'parked';
            $eventType = $isParked ? FuelEvent::TYPE_THEFT_SUSPECTED : FuelEvent::TYPE_DROP;
        }

        if ($eventType === null) {
            return null;
        }

        $event = FuelEvent::create([
            'truck_id' => $truck->id,
            'event_type' => $eventType,
            'litres_delta' => $delta,
            'litres_before' => round($litresBefore, 2),
            'litres_after' => round($litresAfter, 2),
            'odometer_km' => $snapshotAfter->odometer_km,
            'latitude' => $snapshotAfter->latitude,
            'longitude' => $snapshotAfter->longitude,
            'ignition_on' => $snapshotAfter->ignition_on,
            'detected_at' => $snapshotAfter->recorded_at ?? now(),
            'snapshot_before_id' => $snapshotBefore->id,
            'snapshot_after_id' => $snapshotAfter->id,
        ]);

        if ($eventType === FuelEvent::TYPE_THEFT_SUSPECTED) {
            $this->raiseTheftAlert($truck, $event);
            $this->openTheftIncident($truck, $event);
        }

        return $event;
    }

    private function openTheftIncident(Truck $truck, FuelEvent $event): void
    {
        if ($this->theftIncidentService === null) {
            return;  // back-compat: older wiring without the service
        }

        try {
            $this->theftIncidentService->open([
                'truck_id' => $truck->id,
                'fuel_event_id' => $event->id,
                'type' => TheftIncident::TYPE_FUEL_SIPHONING,
                'severity' => abs((float) $event->litres_delta) >= 50
                    ? TheftIncident::SEVERITY_HIGH
                    : TheftIncident::SEVERITY_MEDIUM,
                'detected_at' => $event->detected_at,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'title' => sprintf(
                    'Vol de carburant suspecté (%s L) sur %s',
                    number_format((float) $event->litres_delta, 1, ',', ' '),
                    $truck->matricule ?? '#' . $truck->id
                ),
                'evidence' => [
                    'dedup_key' => 'fuel_siphoning:fuel_event=' . $event->id,
                    'litres_before' => $event->litres_before,
                    'litres_after' => $event->litres_after,
                    'litres_delta' => $event->litres_delta,
                    'ignition_on' => $event->ignition_on,
                    'snapshot_before_id' => $event->snapshot_before_id,
                    'snapshot_after_id' => $event->snapshot_after_id,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to open fuel theft incident', [
                'truck_id' => $truck->id,
                'fuel_event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function raiseTheftAlert(Truck $truck, FuelEvent $event): void
    {
        try {
            LogisticsAlert::firstOrCreate(
                [
                    'type' => 'fuel_theft_suspected',
                    'truck_id' => $truck->id,
                    'checklist_date' => $event->detected_at->toDateString(),
                ],
                [
                    'message' => sprintf(
                        'Baisse de carburant suspecte détectée sur le camion %s: %.2f L → %.2f L (Δ %.2f L) à l\'arrêt.',
                        $truck->matricule,
                        $event->litres_before,
                        $event->litres_after,
                        $event->litres_delta
                    ),
                ]
            );
        } catch (\Throwable $e) {
            // Logistics alerts are best-effort; never block the sync on an alert failure.
            \Illuminate\Support\Facades\Log::warning('Failed to raise fuel theft alert', [
                'truck_id' => $truck->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
