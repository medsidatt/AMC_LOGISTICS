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
    public const NEAREST_SEARCH_KM = 1500.0;

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

        // Idle = engine on while stationary. Fleeti's `ignition_on` flag is unreliable
        // (often null), so we rely primarily on `movement_status='idle'` (which Fleeti
        // sets when engine is running and the truck isn't moving) AND fall back to
        // `(ignition_on=true AND speed<3)` for sources that do populate ignition.
        $snapshots = TruckTelemetrySnapshot::query()
            ->whereIn('truck_id', $truckIds)
            ->whereBetween('recorded_at', [$from, $to])
            ->where(function ($q) {
                $q->where('movement_status', 'idle')
                  ->orWhere(function ($q2) {
                      $q2->where('ignition_on', true)
                         ->where('speed_kmh', '<', self::SPEED_THRESHOLD_KMH);
                  });
            })
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
        $nearestCache = [];
        $rows = collect();

        foreach ($buckets as $bucket) {
            $idleMinutes = $this->idleMinutes($bucket['snapshots'], $bucket['hour_start']);
            if ($idleMinutes < self::MIN_REPORTABLE_MINUTES) {
                continue;
            }

            [$lat, $lng] = $this->centroid($bucket['snapshots']);
            $place = $this->resolvePlace($lat, $lng, $placeCache);
            $classification = $this->classify($place);
            $category = $this->category($place);

            $nearestQuarry = $this->resolveNearest($lat, $lng, Place::TYPE_PROVIDER_SITE, $nearestCache);
            $nearestClient = $this->resolveNearest($lat, $lng, Place::TYPE_CLIENT_SITE, $nearestCache);

            $locationLabel = $this->buildLocationLabel($place, $nearestQuarry, $nearestClient);

            $rows->push([
                'truck_id' => $bucket['truck_id'],
                'truck_matricule' => $matricules[$bucket['truck_id']] ?? '-',
                'date' => $bucket['hour_start']->format('Y-m-d'),
                'hour' => (int) $bucket['hour_start']->format('G'),
                'idle_minutes' => round($idleMinutes, 1),
                'location_label' => $locationLabel,
                'classification' => $classification,
                'category' => $category,
                'place_id' => $place?->id,
                'place_type' => $place?->type,
                'nearest_quarry_name' => $nearestQuarry['name'] ?? null,
                'nearest_quarry_km' => $nearestQuarry ? round($nearestQuarry['km'], 2) : null,
                'nearest_client_name' => $nearestClient['name'] ?? null,
                'nearest_client_km' => $nearestClient ? round($nearestClient['km'], 2) : null,
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

    /**
     * Fine-grained idle category — distinguishes parking, quarry, client base,
     * fleet hub, fuel station, on-road, etc. Used by the report UI to bucket
     * idle minutes by infrastructure type.
     */
    public const CATEGORY_PARKING = 'parking';
    public const CATEGORY_PROVIDER = 'provider_site';
    public const CATEGORY_CLIENT = 'client_site';
    public const CATEGORY_BASE = 'base';
    public const CATEGORY_FUEL = 'fuel_station';
    public const CATEGORY_OTHER_PLACE = 'other_place';
    public const CATEGORY_ON_ROAD = 'on_road';

    private function category(?Place $place): string
    {
        if ($place === null) {
            return self::CATEGORY_ON_ROAD;
        }
        return match ($place->type) {
            Place::TYPE_PARKING => self::CATEGORY_PARKING,
            Place::TYPE_PROVIDER_SITE => self::CATEGORY_PROVIDER,
            Place::TYPE_CLIENT_SITE => self::CATEGORY_CLIENT,
            Place::TYPE_BASE => self::CATEGORY_BASE,
            Place::TYPE_FUEL_STATION => self::CATEGORY_FUEL,
            default => self::CATEGORY_OTHER_PLACE,
        };
    }

    /**
     * @return array{name: string, km: float}|null
     */
    private function resolveNearest(float $lat, float $lng, string $type, array &$cache): ?array
    {
        $key = $type . '|' . round($lat, self::COORD_CACHE_PRECISION) . ',' . round($lng, self::COORD_CACHE_PRECISION);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $place = $this->geo->nearestPlace($lat, $lng, $type, self::NEAREST_SEARCH_KM);
        if ($place === null) {
            return $cache[$key] = null;
        }

        $km = $this->geo->haversineKm($lat, $lng, (float) $place->latitude, (float) $place->longitude);
        return $cache[$key] = ['name' => $place->name, 'km' => $km];
    }

    /**
     * @param  array{name: string, km: float}|null  $quarry
     * @param  array{name: string, km: float}|null  $client
     */
    private function buildLocationLabel(?Place $place, ?array $quarry, ?array $client): string
    {
        if ($place !== null) {
            return $place->name;
        }

        // Outside any geofence: pick the closer of carrière vs client to label "near …".
        $candidate = null;
        if ($quarry && $client) {
            $candidate = $quarry['km'] <= $client['km']
                ? ['label' => 'Carrière', ...$quarry]
                : ['label' => 'Client', ...$client];
        } elseif ($quarry) {
            $candidate = ['label' => 'Carrière', ...$quarry];
        } elseif ($client) {
            $candidate = ['label' => 'Client', ...$client];
        }

        if ($candidate) {
            return sprintf('Sur route — à %.1f km de %s (%s)', $candidate['km'], $candidate['name'], $candidate['label']);
        }

        return 'Sur route';
    }
}
