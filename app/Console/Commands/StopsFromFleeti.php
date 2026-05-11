<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Models\Truck;
use App\Services\FleetiService;
use App\Services\GeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pulls historical telemetry directly from Fleeti's
 *   /v1/Asset/History/SearchAllValues
 * endpoint and reconstructs each stop episode (speed < threshold for >= min
 * duration). Each stop is then resolved against the local `places` table
 * (geofence containment) so you can prove "where the truck actually was".
 *
 * Usage:
 *   php artisan stops:from-fleeti --truck=6074 --truck=6066 --truck=6081 \
 *     --from=2026-04-20 --to=2026-04-26
 *
 * Optional:
 *   --speed=3        speed threshold km/h to count as stopped (default 3)
 *   --min=10         minimum stop duration in minutes (default 10)
 *   --customer=XXX   override CustomerReference (auto-resolved if omitted)
 *   --raw            also print raw telemetry record count per truck
 */
class StopsFromFleeti extends Command
{
    protected $signature = 'stops:from-fleeti
        {--truck=* : Matricule pattern (partial match, repeatable)}
        {--from= : Start date YYYY-MM-DD (required)}
        {--to= : End date YYYY-MM-DD (required)}
        {--speed=3 : Speed threshold in km/h to count as "stopped"}
        {--min=10 : Minimum stop duration in minutes}
        {--customer= : Fleeti CustomerReference (auto-resolved if omitted)}
        {--raw : Print raw record counts}';

    protected $description = 'Reconstruct truck stops directly from Fleeti history API and resolve their location';

    public function handle(FleetiService $fleeti, GeoService $geo): int
    {
        if (! $this->option('from') || ! $this->option('to')) {
            $this->error('--from and --to are required (YYYY-MM-DD).');
            return self::FAILURE;
        }

        $from = Carbon::parse($this->option('from'))->startOfDay();
        $to = Carbon::parse($this->option('to'))->endOfDay();
        $speedThreshold = (float) $this->option('speed');
        $minMinutes = (float) $this->option('min');

        $patterns = (array) $this->option('truck');
        $trucksQuery = Truck::query()->whereNotNull('fleeti_asset_id');
        if (! empty($patterns)) {
            $trucksQuery->where(function ($q) use ($patterns) {
                foreach ($patterns as $p) {
                    $q->orWhere('matricule', 'like', '%'.$p.'%');
                }
            });
        }
        $trucks = $trucksQuery->get(['id', 'matricule', 'fleeti_asset_id']);

        if ($trucks->isEmpty()) {
            $this->error('No trucks with fleeti_asset_id matched.');
            return self::FAILURE;
        }

        $customer = (string) ($this->option('customer') ?? '');
        if ($customer === '') {
            $customer = $this->resolveCustomer();
            if ($customer === null) {
                $this->error('Could not resolve Fleeti customer reference. Pass --customer=XXX.');
                return self::FAILURE;
            }
            $this->info("CustomerReference: {$customer}");
        }

        $fromIso = $from->toIso8601String();
        $toIso = $to->toIso8601String();
        $assetIds = $trucks->pluck('fleeti_asset_id')->all();

        $this->info(sprintf(
            'Fetching Fleeti history: %d truck(s) · %s → %s',
            $trucks->count(), $from->format('Y-m-d'), $to->format('Y-m-d'),
        ));

        $records = $fleeti->fetchAllValues($customer, $fromIso, $toIso, $assetIds);
        $this->line('  → '.$records->count().' raw telemetry records returned');

        if ($records->isEmpty()) {
            $this->warn('No telemetry records in this window. Check --from/--to or Fleeti permissions.');
            return self::SUCCESS;
        }

        $byAsset = $records->groupBy(fn ($r) => (string) data_get($r, 'assetId'));

        $allStops = collect();
        foreach ($trucks as $truck) {
            $assetRecords = $byAsset->get($truck->fleeti_asset_id, collect());

            if ($this->option('raw')) {
                $this->line(sprintf('  %s (%s): %d records', $truck->matricule, $truck->fleeti_asset_id, $assetRecords->count()));
            }

            $stops = $this->detectStops($assetRecords, $speedThreshold, $minMinutes * 60);
            foreach ($stops as $s) {
                $s['truck'] = $truck->matricule;
                $allStops->push($s);
            }
        }

        if ($allStops->isEmpty()) {
            $this->warn('No stops detected with the given thresholds.');
            return self::SUCCESS;
        }

        $rows = $allStops->map(function ($s) use ($geo) {
            $place = $geo->nearestCoveringPlace($s['lat'], $s['lng']);
            $category = $place?->type ?? 'on_road';
            $location = $place ? $place->name : 'Sur route';

            if (! $place) {
                $nearest = $geo->nearestPlace($s['lat'], $s['lng'], null, 50.0);
                if ($nearest) {
                    $km = $geo->haversineKm($s['lat'], $s['lng'], (float) $nearest->latitude, (float) $nearest->longitude);
                    $location = sprintf('Sur route (à %.1f km de %s)', $km, $nearest->name);
                }
            }

            return [
                'truck' => $s['truck'],
                'started' => $s['started']->format('Y-m-d H:i'),
                'ended' => $s['ended']->format('Y-m-d H:i'),
                'duration' => $this->humanDuration((int) $s['duration_seconds']),
                'category' => $this->categoryLabel($category),
                'location' => $location,
                'lat' => number_format($s['lat'], 5),
                'lon' => number_format($s['lng'], 5),
                'samples' => $s['samples'],
            ];
        })->sortBy([['truck', 'asc'], ['started', 'asc']])->values();

        $this->newLine();
        $this->info(sprintf('Stops détectés (speed < %s km/h, durée ≥ %s min)', $speedThreshold, $minMinutes));
        $this->table(
            ['camion', 'début', 'fin', 'durée', 'catégorie', 'lieu', 'lat', 'lon', 'pts'],
            $rows->map(fn ($r) => [
                $r['truck'], $r['started'], $r['ended'], $r['duration'],
                $r['category'], Str::limit($r['location'], 60),
                $r['lat'], $r['lon'], $r['samples'],
            ])->all(),
        );

        $this->newLine();
        $this->info('Récapitulatif par catégorie');
        $totalSec = (int) $allStops->sum('duration_seconds');
        $byCat = $allStops->groupBy(function ($s) use ($geo) {
            $place = $geo->nearestCoveringPlace($s['lat'], $s['lng']);
            return $place?->type ?? 'on_road';
        });

        $this->table(
            ['catégorie', 'arrêts', 'durée totale', '% du temps arrêté'],
            $byCat->map(function ($g, $cat) use ($totalSec) {
                $sec = (int) $g->sum('duration_seconds');
                return [
                    $this->categoryLabel($cat),
                    $g->count(),
                    $this->humanDuration($sec),
                    $totalSec > 0 ? number_format($sec * 100.0 / $totalSec, 1).' %' : '—',
                ];
            })->values()->all(),
        );

        $this->newLine();
        $this->info('Récapitulatif par camion');
        $this->table(
            ['camion', 'arrêts', 'durée totale arrêtée'],
            $allStops->groupBy('truck')->map(fn ($g, $matricule) => [
                $matricule,
                $g->count(),
                $this->humanDuration((int) $g->sum('duration_seconds')),
            ])->values()->all(),
        );

        return self::SUCCESS;
    }

    /**
     * Walk records chronologically and emit stop episodes.
     *
     * @return array<int, array{started:Carbon, ended:Carbon, duration_seconds:int, lat:float, lng:float, samples:int}>
     */
    private function detectStops(Collection $records, float $speedThreshold, int $minSeconds): array
    {
        $points = $records
            ->map(function ($r) {
                $ts = data_get($r, 'date')
                    ?? data_get($r, 'recordedAt')
                    ?? data_get($r, 'timestamp')
                    ?? data_get($r, 'createdAt');
                $lat = data_get($r, 'latitude') ?? data_get($r, 'position.latitude') ?? data_get($r, 'lat');
                $lng = data_get($r, 'longitude') ?? data_get($r, 'position.longitude') ?? data_get($r, 'lng');
                $speed = data_get($r, 'speed') ?? data_get($r, 'speedKmh') ?? data_get($r, 'velocity');

                if (! $ts || ! is_numeric($lat) || ! is_numeric($lng)) {
                    return null;
                }
                try {
                    $t = Carbon::parse($ts);
                } catch (\Throwable) {
                    return null;
                }

                return [
                    't' => $t,
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'speed' => is_numeric($speed) ? (float) $speed : 0.0,
                ];
            })
            ->filter()
            ->sortBy('t')
            ->values();

        $stops = [];
        $current = null;

        foreach ($points as $p) {
            if ($p['speed'] <= $speedThreshold) {
                if ($current === null) {
                    $current = [
                        'started' => $p['t'],
                        'ended' => $p['t'],
                        'lat_sum' => $p['lat'],
                        'lng_sum' => $p['lng'],
                        'samples' => 1,
                    ];
                } else {
                    $current['ended'] = $p['t'];
                    $current['lat_sum'] += $p['lat'];
                    $current['lng_sum'] += $p['lng'];
                    $current['samples']++;
                }
            } else {
                if ($current !== null) {
                    $duration = $current['started']->diffInSeconds($current['ended']);
                    if ($duration >= $minSeconds) {
                        $stops[] = $this->finalizeStop($current);
                    }
                    $current = null;
                }
            }
        }

        if ($current !== null) {
            $duration = $current['started']->diffInSeconds($current['ended']);
            if ($duration >= $minSeconds) {
                $stops[] = $this->finalizeStop($current);
            }
        }

        return $stops;
    }

    private function finalizeStop(array $cur): array
    {
        return [
            'started' => $cur['started'],
            'ended' => $cur['ended'],
            'duration_seconds' => $cur['started']->diffInSeconds($cur['ended']),
            'lat' => $cur['lat_sum'] / $cur['samples'],
            'lng' => $cur['lng_sum'] / $cur['samples'],
            'samples' => $cur['samples'],
        ];
    }

    private function resolveCustomer(): ?string
    {
        $base = rtrim((string) config('services.fleeti.base_url'), '/');
        $headers = ['x-api-key' => (string) config('services.fleeti.api_key'), 'Accept' => 'application/json'];
        if ($bearer = (string) config('services.fleeti.bearer_token', '')) {
            $headers['Authorization'] = 'Bearer '.$bearer;
        }
        $resp = Http::withHeaders($headers)->timeout(20)->get($base.'/v1/Customer/Search', ['Skip' => 0, 'Take' => 1]);
        return $resp->successful() ? $resp->json('results.0.reference') : null;
    }

    private function categoryLabel(string $cat): string
    {
        return match ($cat) {
            Place::TYPE_PARKING => 'Parking',
            Place::TYPE_PROVIDER_SITE => 'Carrière',
            Place::TYPE_CLIENT_SITE => 'Base client',
            Place::TYPE_BASE => 'Base / Hub',
            Place::TYPE_FUEL_STATION => 'Station',
            'on_road' => 'Sur route',
            default => $cat,
        };
    }

    private function humanDuration(int $seconds): string
    {
        if ($seconds <= 0) return '<1m';
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($d) $parts[] = $d.'d';
        if ($h) $parts[] = $h.'h';
        if ($m) $parts[] = $m.'m';
        return $parts ? implode(' ', $parts) : '<1m';
    }
}
