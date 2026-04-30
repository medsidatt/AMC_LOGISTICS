<?php

namespace App\Services;

use App\Models\Place;
use App\Models\Truck;
use App\Models\TruckTelemetrySnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IdleHourlyReportService
{
    public const SPEED_THRESHOLD_KMH = 3.0;
    public const GAP_CAP_SECONDS = 300;
    public const MIN_REPORTABLE_MINUTES = 1.0;
    public const COORD_CACHE_PRECISION = 5;

    public function __construct(private GeoService $geo) {}

    /**
     * Build hourly idle rows for the given trucks and time range.
     *
     * @param  int[]  $truckIds
     * @return Collection<int, array>
     */
    public function build(array $truckIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if (empty($truckIds)) {
            return collect();
        }

        $matricules = Truck::whereIn('id', $truckIds)->pluck('matricule', 'id');

        $snapshots = TruckTelemetrySnapshot::query()
            ->whereIn('truck_id', $truckIds)
            ->whereBetween('recorded_at', [$from, $to])
            ->where('ignition_on', true)
            ->where('speed_kmh', '<', self::SPEED_THRESHOLD_KMH)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('truck_id')
            ->orderBy('recorded_at')
            ->get(['truck_id', 'recorded_at', 'latitude', 'longitude', 'speed_kmh']);

        $buckets = [];
        foreach ($snapshots as $snap) {
            $hourStart = Carbon::instance($snap->recorded_at)->startOfHour();
            $key = $snap->truck_id . '|' . $hourStart->format('Y-m-d H');
            $buckets[$key] ??= [
                'truck_id' => $snap->truck_id,
                'hour_start' => $hourStart,
                'snapshots' => [],
            ];
            $buckets[$key]['snapshots'][] = $snap;
        }

        $placeCache = [];
        $rows = collect();

        foreach ($buckets as $bucket) {
            $idleMinutes = $this->idleMinutes($bucket['snapshots'], $bucket['hour_start']);
            if ($idleMinutes < self::MIN_REPORTABLE_MINUTES) {
                continue;
            }

            [$lat, $lng] = $this->centroid($bucket['snapshots']);
            $place = $this->resolvePlace($lat, $lng, $placeCache);
            $classification = $this->classify($place);
            $locationLabel = $place?->name ?? 'Sur route';

            $rows->push([
                'truck_id' => $bucket['truck_id'],
                'truck_matricule' => $matricules[$bucket['truck_id']] ?? '-',
                'date' => $bucket['hour_start']->format('Y-m-d'),
                'hour' => (int) $bucket['hour_start']->format('G'),
                'idle_minutes' => round($idleMinutes, 1),
                'location_label' => $locationLabel,
                'classification' => $classification,
                'place_id' => $place?->id,
                'latitude' => round($lat, 6),
                'longitude' => round($lng, 6),
            ]);
        }

        return $rows->sortBy([
            ['truck_matricule', 'asc'],
            ['date', 'asc'],
            ['hour', 'asc'],
        ])->values();
    }

    /**
     * Sum gaps between consecutive idle snapshots within a single hour bucket.
     * Each gap is capped at GAP_CAP_SECONDS to absorb device-offline holes,
     * and the bucket is clipped to [hourStart, hourStart+1h].
     */
    private function idleMinutes(array $snapshots, CarbonInterface $hourStart): float
    {
        if (count($snapshots) < 2) {
            // Single snapshot — credit GAP_CAP/2 each side, clipped to bucket.
            return count($snapshots) === 1
                ? min(60.0, self::GAP_CAP_SECONDS / 60.0)
                : 0.0;
        }

        $hourEnd = $hourStart->copy()->addHour();
        $totalSeconds = 0;

        for ($i = 1; $i < count($snapshots); $i++) {
            $prev = Carbon::instance($snapshots[$i - 1]->recorded_at);
            $curr = Carbon::instance($snapshots[$i]->recorded_at);

            $segStart = $prev->greaterThan($hourStart) ? $prev : $hourStart;
            $segEnd = $curr->lessThan($hourEnd) ? $curr : $hourEnd;

            $diff = $segStart->diffInSeconds($segEnd, false);
            if ($diff <= 0) {
                continue;
            }
            $totalSeconds += min($diff, self::GAP_CAP_SECONDS);
        }

        return min(60.0, $totalSeconds / 60.0);
    }

    /** @return array{0: float, 1: float} */
    private function centroid(array $snapshots): array
    {
        $sumLat = 0.0;
        $sumLng = 0.0;
        $n = count($snapshots);
        foreach ($snapshots as $s) {
            $sumLat += (float) $s->latitude;
            $sumLng += (float) $s->longitude;
        }
        return [$sumLat / $n, $sumLng / $n];
    }

    private function resolvePlace(float $lat, float $lng, array &$cache): ?Place
    {
        $key = round($lat, self::COORD_CACHE_PRECISION) . ',' . round($lng, self::COORD_CACHE_PRECISION);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        return $cache[$key] = $this->geo->nearestCoveringPlace($lat, $lng);
    }

    private function classify(?Place $place): string
    {
        if ($place === null) {
            return 'Sur route';
        }
        return $place->type === Place::TYPE_PROVIDER_SITE ? 'Carrière' : 'Autre site';
    }
}
