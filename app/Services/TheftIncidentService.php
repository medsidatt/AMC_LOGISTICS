<?php

namespace App\Services;

use App\Models\Auth\User;
use App\Models\LogisticsAlert;
use App\Models\TheftIncident;
use App\Models\Truck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Central writer for theft_incidents rows.
 *
 * Every detector calls `open()` instead of writing to the table directly.
 * Idempotency is guaranteed via a deterministic `dedup_key` stored inside
 * the JSON `evidence` blob — the same detector replaying the same input
 * (e.g. a re-sync) will update the existing row rather than inserting a
 * duplicate.
 *
 * Incidents also raise a paired LogisticsAlert for visibility in the
 * existing logistics stream (best-effort, non-blocking).
 */
class TheftIncidentService
{
    /**
     * Open (or update) a theft incident.
     *
     * @param  array  $attrs  At minimum: truck_id, type, title, detected_at, severity,
     *                        and evidence.dedup_key. Optional FKs: transport_tracking_id,
     *                        trip_segment_id, truck_stop_id, fuel_event_id.
     */
    public function open(array $attrs): TheftIncident
    {
        $truckId = (int) ($attrs['truck_id'] ?? 0);
        $type = (string) ($attrs['type'] ?? '');
        $evidence = (array) ($attrs['evidence'] ?? []);
        $dedupKey = (string) ($evidence['dedup_key'] ?? $this->defaultDedupKey($type, $attrs));
        $evidence['dedup_key'] = $dedupKey;

        $detectedAt = $this->parseDate($attrs['detected_at'] ?? null) ?? Carbon::now();

        $severity = $attrs['severity'] ?? TheftIncident::SEVERITY_MEDIUM;
        $status = $attrs['status'] ?? TheftIncident::STATUS_PENDING;
        $title = $attrs['title'] ?? $this->defaultTitle($type);

        $payload = [
            'truck_id' => $truckId,
            'transport_tracking_id' => $attrs['transport_tracking_id'] ?? null,
            'trip_segment_id' => $attrs['trip_segment_id'] ?? null,
            'truck_stop_id' => $attrs['truck_stop_id'] ?? null,
            'fuel_event_id' => $attrs['fuel_event_id'] ?? null,
            'type' => $type,
            'severity' => $severity,
            'status' => $status,
            'detected_at' => $detectedAt,
            'latitude' => $attrs['latitude'] ?? null,
            'longitude' => $attrs['longitude'] ?? null,
            'title' => $title,
            'evidence' => $evidence,
        ];

        // Look up any existing row with the same dedup_key for this truck + type.
        // We can't use a DB unique index on JSON fields portably, so we match
        // via a WHERE clause on the JSON path.
        $existing = TheftIncident::query()
            ->where('truck_id', $truckId)
            ->where('type', $type)
            ->whereJsonContains('evidence->dedup_key', $dedupKey)
            ->first();

        if ($existing) {
            $existing->fill($payload)->save();
            $incident = $existing->fresh();
        } else {
            $incident = TheftIncident::create($payload);
        }

        $this->raisePairedAlert($incident);

        return $incident;
    }

    public function markReviewed(TheftIncident $incident, ?User $by, ?string $notes = null): TheftIncident
    {
        $incident->update([
            'status' => TheftIncident::STATUS_REVIEWED,
            'reviewed_by' => $by?->id,
            'reviewed_at' => Carbon::now(),
            'review_notes' => $notes,
        ]);

        return $incident->fresh();
    }

    public function markDismissed(TheftIncident $incident, ?User $by, ?string $notes = null): TheftIncident
    {
        $incident->update([
            'status' => TheftIncident::STATUS_DISMISSED,
            'reviewed_by' => $by?->id,
            'reviewed_at' => Carbon::now(),
            'review_notes' => $notes,
        ]);

        return $incident->fresh();
    }

    public function markConfirmed(TheftIncident $incident, ?User $by, ?string $notes = null): TheftIncident
    {
        $incident->update([
            'status' => TheftIncident::STATUS_CONFIRMED,
            'reviewed_by' => $by?->id,
            'reviewed_at' => Carbon::now(),
            'review_notes' => $notes,
        ]);

        return $incident->fresh();
    }

    private function defaultDedupKey(string $type, array $attrs): string
    {
        // Stable key for each incident type. Stored inside evidence so a
        // re-run of the detector updates instead of duplicating.
        return match ($type) {
            TheftIncident::TYPE_FUEL_SIPHONING => 'fuel_siphoning:fuel_event=' . ($attrs['fuel_event_id'] ?? 'null'),
            TheftIncident::TYPE_WEIGHT_GAP => 'weight_gap:transport=' . ($attrs['transport_tracking_id'] ?? 'null'),
            TheftIncident::TYPE_UNAUTHORIZED_STOP => 'unauthorized_stop:stop=' . ($attrs['truck_stop_id'] ?? 'null'),
            TheftIncident::TYPE_ROUTE_DEVIATION => 'route_deviation:transport=' . ($attrs['transport_tracking_id'] ?? 'null'),
            TheftIncident::TYPE_OFF_HOURS_MOVEMENT => 'off_hours:truck=' . ($attrs['truck_id'] ?? 'null')
                . ':window=' . ($attrs['_window_key'] ?? Carbon::now()->startOfHour()->toDateTimeString()),
            default => $type . ':truck=' . ($attrs['truck_id'] ?? 'null'),
        };
    }

    private function defaultTitle(string $type): string
    {
        return match ($type) {
            TheftIncident::TYPE_FUEL_SIPHONING => 'Vol de carburant suspecté',
            TheftIncident::TYPE_WEIGHT_GAP => 'Écart de poids cargaison',
            TheftIncident::TYPE_UNAUTHORIZED_STOP => 'Arrêt non autorisé',
            TheftIncident::TYPE_ROUTE_DEVIATION => 'Déviation d\'itinéraire',
            TheftIncident::TYPE_OFF_HOURS_MOVEMENT => 'Mouvement hors horaires',
            default => 'Incident détecté',
        };
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mirror the incident into the logistics alerts stream so Managers see it
     * next to the other alert types. Best-effort: never block the caller.
     */
    private function raisePairedAlert(TheftIncident $incident): void
    {
        try {
            $truck = Truck::find($incident->truck_id);
            $matricule = $truck?->matricule ?? '#' . $incident->truck_id;

            $alertType = match ($incident->type) {
                TheftIncident::TYPE_FUEL_SIPHONING => 'fuel_theft_suspected',
                TheftIncident::TYPE_WEIGHT_GAP => 'weight_gap_detected',
                TheftIncident::TYPE_UNAUTHORIZED_STOP => 'unauthorized_stop_detected',
                TheftIncident::TYPE_ROUTE_DEVIATION => 'route_deviation_detected',
                TheftIncident::TYPE_OFF_HOURS_MOVEMENT => 'off_hours_movement_detected',
                default => 'theft_incident',
            };

            LogisticsAlert::firstOrCreate(
                [
                    'type' => $alertType,
                    'truck_id' => $incident->truck_id,
                    'checklist_date' => $incident->detected_at->toDateString(),
                ],
                [
                    'message' => sprintf('[%s] %s', $matricule, $incident->title),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to mirror theft incident into logistics alerts.', [
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
