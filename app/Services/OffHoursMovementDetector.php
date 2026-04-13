<?php

namespace App\Services;

use App\Models\TheftIncident;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Models\TruckTelemetrySnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Scans recent telemetry for trucks that moved outside configured work hours
 * AND outside any active transport mission. Designed to run hourly from a
 * scheduled command.
 *
 * An incident is deduped per (truck, hour window) so multiple snapshots in the
 * same hour collapse to a single alert.
 */
class OffHoursMovementDetector
{
    public function __construct(private readonly TheftIncidentService $theftIncidentService)
    {
    }

    /**
     * Inspect the last $windowMinutes of telemetry for all trucks.
     *
     * @return TheftIncident[]
     */
    public function runOverRecentWindow(int $windowMinutes = 120): array
    {
        $now = Carbon::now();
        $from = $now->copy()->subMinutes($windowMinutes);

        $minSpeed = (float) config('maintenance.off_hours_min_speed_kmh', 5);

        $movingSnapshots = TruckTelemetrySnapshot::query()
            ->whereBetween('recorded_at', [$from, $now])
            ->where('speed_kmh', '>=', $minSpeed)
            ->orderBy('truck_id')
            ->orderBy('recorded_at')
            ->get();

        if ($movingSnapshots->isEmpty()) {
            return [];
        }

        $incidents = [];

        // Group by truck + hour window to dedupe.
        $groups = $movingSnapshots->groupBy(function (TruckTelemetrySnapshot $s) {
            $recordedAt = $s->recorded_at ?? $s->synced_at ?? Carbon::now();
            return $s->truck_id . ':' . $recordedAt->format('Y-m-d H');
        });

        foreach ($groups as $groupKey => $snapshots) {
            /** @var Collection<int, TruckTelemetrySnapshot> $snapshots */
            $first = $snapshots->first();
            $recordedAt = $first->recorded_at ?? $first->synced_at ?? Carbon::now();

            if ($this->isDuringWorkHours($recordedAt)) {
                continue;  // within work hours, ignore
            }

            if ($this->hasActiveTransport($first->truck_id, $recordedAt)) {
                continue;  // legitimate off-hours mission (e.g. night delivery)
            }

            $truck = Truck::find($first->truck_id);
            if (! $truck) {
                continue;
            }

            $matricule = $truck->matricule ?? '#' . $first->truck_id;
            $windowKey = $recordedAt->copy()->startOfHour()->toDateTimeString();

            $incident = $this->theftIncidentService->open([
                'truck_id' => $first->truck_id,
                'type' => TheftIncident::TYPE_OFF_HOURS_MOVEMENT,
                'severity' => TheftIncident::SEVERITY_MEDIUM,
                'detected_at' => $recordedAt,
                'latitude' => $first->latitude,
                'longitude' => $first->longitude,
                'title' => sprintf(
                    'Mouvement hors horaires détecté (%s à %s)',
                    $matricule,
                    $recordedAt->format('d/m/Y H:i')
                ),
                '_window_key' => $windowKey,
                'evidence' => [
                    'dedup_key' => 'off_hours:truck=' . $first->truck_id . ':window=' . $windowKey,
                    'window_start' => $recordedAt->copy()->startOfHour()->toIso8601String(),
                    'window_end' => $recordedAt->copy()->endOfHour()->toIso8601String(),
                    'snapshot_count' => $snapshots->count(),
                    'max_speed_kmh' => (float) $snapshots->max('speed_kmh'),
                ],
            ]);

            $incidents[] = $incident;
        }

        return $incidents;
    }

    private function isDuringWorkHours(Carbon $moment): bool
    {
        $start = (string) config('maintenance.work_hours.start', '05:00');
        $end = (string) config('maintenance.work_hours.end', '21:00');
        $days = (array) config('maintenance.work_hours.days', [1, 2, 3, 4, 5, 6]);

        if (! in_array($moment->dayOfWeekIso, array_map('intval', $days), true)) {
            return false;
        }

        [$startH, $startM] = array_map('intval', explode(':', $start) + [0, 0]);
        [$endH, $endM] = array_map('intval', explode(':', $end) + [0, 0]);

        $startMoment = $moment->copy()->setTime($startH, $startM, 0);
        $endMoment = $moment->copy()->setTime($endH, $endM, 0);

        return $moment->between($startMoment, $endMoment);
    }

    private function hasActiveTransport(int $truckId, Carbon $moment): bool
    {
        $date = $moment->copy()->toDateString();

        return TransportTracking::query()
            ->where('truck_id', $truckId)
            ->where(function ($q) use ($date) {
                $q->whereDate('provider_date', $date)
                    ->orWhereDate('client_date', $date);
            })
            ->exists();
    }
}
