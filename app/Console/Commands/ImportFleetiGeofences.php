<?php

namespace App\Console\Commands;

use App\Models\Place;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Imports Fleeti geofences/POIs from /v1/Provider/SearchLocation
 * (LocationType=1=Geofence, ProviderType=10=TrackingFleeti) into the
 * local `places` table.
 *
 * Each Fleeti geofence becomes one Place keyed by `code = "FLEETI-{id}"`,
 * so re-running is idempotent. Polygons are stored using the polygon's
 * centroid + bounding-circle radius (rough but workable).
 */
class ImportFleetiGeofences extends Command
{
    protected $signature = 'fleeti:import-geofences
        {--customer= : CustomerReference (defaults to first customer found)}
        {--dry : List what would be imported without writing}
        {--include-polygons : Also import polygon zones (otherwise circles only)}
        {--max-radius=5000 : Skip geofences with computed radius greater than this many metres}';

    protected $description = 'Import Fleeti geofences as Places';

    public function handle(): int
    {
        $base = rtrim((string) config('services.fleeti.base_url'), '/');
        $key = (string) config('services.fleeti.api_key');
        if ($key === '') {
            $this->error('FLEETI_API_KEY is not configured.');
            return self::FAILURE;
        }
        $headers = ['x-api-key' => $key, 'Accept' => 'application/json'];
        $bearer = (string) config('services.fleeti.bearer_token', '');
        if ($bearer !== '') {
            $headers['Authorization'] = 'Bearer ' . $bearer;
        }

        $customer = (string) ($this->option('customer') ?? '');
        if ($customer === '') {
            $customer = $this->resolveCustomer($base, $headers);
            if ($customer === null) {
                $this->error('No customer found and --customer was not provided.');
                return self::FAILURE;
            }
            $this->info("Using CustomerReference: {$customer}");
        }

        $resp = Http::withHeaders($headers)->timeout(30)->get($base . '/v1/Provider/SearchLocation', [
            'CustomerReference' => $customer,
            'ProviderType' => 10,
            'LocationType' => 1,
            'Skip' => 0,
            'Take' => 500,
        ]);
        if (!$resp->successful()) {
            $this->error("SearchLocation failed: HTTP {$resp->status()} — " . $resp->body());
            return self::FAILURE;
        }

        $items = $resp->json('results', []);
        $this->info('Geofences returned: ' . count($items));

        $imported = 0;
        $maxRadius = (int) $this->option('max-radius');
        $includePolygons = (bool) $this->option('include-polygons');

        foreach ($items as $g) {
            $isCircle = !empty($g['center']) && isset($g['radius']);
            if (!$isCircle && !$includePolygons) {
                $this->line(' skip polygon: ' . ($g['label'] ?? '?') . ' (use --include-polygons to keep)');
                continue;
            }

            [$lat, $lng, $radiusM] = $this->extractCircle($g);
            if ($lat === null || $lng === null) {
                $this->warn(' skip "' . ($g['label'] ?? '?') . '" — no coords');
                continue;
            }
            if ($radiusM > $maxRadius) {
                $this->line(sprintf(' skip "%s" — radius %dm > max %dm', $g['label'] ?? '?', $radiusM, $maxRadius));
                continue;
            }

            $code = 'FLEETI-' . ($g['id'] ?? md5(json_encode($g)));
            $name = $g['label'] ?? "Geofence {$g['id']}";

            $this->line(sprintf(
                ' %s  %s  (%.5f, %.5f) r=%dm',
                $this->option('dry') ? '[dry]' : '✓',
                $name,
                $lat,
                $lng,
                $radiusM
            ));

            if ($this->option('dry')) {
                continue;
            }

            Place::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => Place::TYPE_UNKNOWN,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'radius_m' => $radiusM,
                    'is_active' => true,
                    'is_auto_detected' => false,
                    'notes' => 'Imported from Fleeti geofence ' . ($g['id'] ?? '?')
                        . '. Set type=provider_site for carrières or client_site for client deliveries.',
                ]
            );
            $imported++;
        }

        $this->info("Imported/updated: {$imported}");
        $this->line('Set the correct `type` on each (provider_site / client_site) at /logistics/places.');

        return self::SUCCESS;
    }

    private function resolveCustomer(string $base, array $headers): ?string
    {
        $resp = Http::withHeaders($headers)->timeout(20)->get($base . '/v1/Customer/Search', ['Skip' => 0, 'Take' => 1]);
        return $resp->json('results.0.reference');
    }

    /**
     * Returns [lat, lng, radius_m] for the geofence, regardless of polygon vs circle.
     *
     * @return array{0: float|null, 1: float|null, 2: int}
     */
    private function extractCircle(array $g): array
    {
        // Type 1 = circle (has center + radius)
        $center = $g['center'] ?? null;
        if ($center && isset($center['latitude'], $center['longitude'])) {
            return [
                (float) $center['latitude'],
                (float) $center['longitude'],
                (int) round($g['radius'] ?? 200),
            ];
        }

        // Type 2 = polygon — use centroid + half-diagonal as radius
        $points = $g['points'] ?? [];
        if (!empty($points)) {
            $lat = array_sum(array_column($points, 'latitude')) / count($points);
            $lng = array_sum(array_column($points, 'longitude')) / count($points);
            $radius = $this->boundsRadiusM($g['bounds'] ?? null) ?: 1000;
            return [$lat, $lng, $radius];
        }

        // Fallback: use bounds centroid
        if (!empty($g['bounds'])) {
            $b = $g['bounds'];
            $lat = ($b['nw']['latitude'] + $b['se']['latitude']) / 2;
            $lng = ($b['nw']['longitude'] + $b['se']['longitude']) / 2;
            return [$lat, $lng, $this->boundsRadiusM($b) ?: 1000];
        }

        return [null, null, 0];
    }

    private function boundsRadiusM(?array $bounds): int
    {
        if (!$bounds) {
            return 0;
        }
        // Half the great-circle distance between NW and SE corners.
        $lat1 = deg2rad($bounds['nw']['latitude']);
        $lat2 = deg2rad($bounds['se']['latitude']);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($bounds['se']['longitude'] - $bounds['nw']['longitude']);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return (int) round(6371000 * $c / 2);
    }
}
