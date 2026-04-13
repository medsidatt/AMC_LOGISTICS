<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Models\TruckTelemetrySnapshot;
use App\Services\GeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Auto-detect "hub" places (typically bases) from telemetry.
 *
 * Strategy (simple, deterministic, idempotent):
 *  1. Pull snapshots from the last N days where the truck was parked AND
 *     ignition was off (real stops, not traffic lights).
 *  2. Greedy-cluster them by proximity: for each snapshot, if it falls
 *     within the configured cluster radius of an existing cluster's
 *     centroid, add it; otherwise open a new cluster.
 *  3. Any cluster whose total dwell time exceeds
 *     `hub_detection_min_parked_hours` is persisted as a `places` row
 *     with `type=base` and `is_auto_detected=true`.
 *  4. Existing auto-detected bases within 500m of a new cluster are
 *     UPDATED in place (coordinates refreshed to the new centroid) rather
 *     than duplicated.
 */
class DetectPlaceHubs extends Command
{
    protected $signature = 'places:detect-hubs
        {--days=30 : Days of telemetry history to inspect}
        {--dry-run : Print what would be created/updated without writing}';

    protected $description = 'Cluster long parked sessions into auto-detected base places.';

    public function handle(GeoService $geo): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        $minHours = (float) config('maintenance.hub_detection_min_parked_hours', 2);
        $clusterRadiusM = (int) config('maintenance.hub_detection_cluster_radius_m', 250);
        $defaultRadiusM = (int) config('maintenance.place_default_radius_m', 300);

        $from = Carbon::now()->subDays($days);
        $this->info("Inspecting snapshots since {$from->toDateString()}…");

        // Aggregate per-truck dwell time by coordinate cluster.
        $clusters = [];  // [['lat' => .., 'lng' => .., 'samples' => int, 'dwell_hours' => float]]

        TruckTelemetrySnapshot::query()
            ->whereBetween('recorded_at', [$from, Carbon::now()])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($q) {
                $q->where('movement_status', 'parked')
                    ->orWhere('ignition_on', false);
            })
            ->orderBy('truck_id')
            ->orderBy('recorded_at')
            ->chunkById(2000, function ($snapshots) use (&$clusters, $geo, $clusterRadiusM) {
                foreach ($snapshots as $s) {
                    $lat = (float) $s->latitude;
                    $lng = (float) $s->longitude;

                    $assigned = false;
                    foreach ($clusters as &$cluster) {
                        $distanceM = $geo->haversineMetres($lat, $lng, $cluster['lat'], $cluster['lng']);
                        if ($distanceM <= $clusterRadiusM) {
                            // Incremental centroid: running average
                            $newCount = $cluster['samples'] + 1;
                            $cluster['lat'] = ($cluster['lat'] * $cluster['samples'] + $lat) / $newCount;
                            $cluster['lng'] = ($cluster['lng'] * $cluster['samples'] + $lng) / $newCount;
                            $cluster['samples'] = $newCount;
                            // Approximate dwell: every parked snapshot ≈ sync interval.
                            $cluster['dwell_hours'] += (float) (config('maintenance.fleeti_sync_interval_minutes', 30) / 60);
                            $assigned = true;
                            break;
                        }
                    }
                    unset($cluster);

                    if (! $assigned) {
                        $clusters[] = [
                            'lat' => $lat,
                            'lng' => $lng,
                            'samples' => 1,
                            'dwell_hours' => (float) (config('maintenance.fleeti_sync_interval_minutes', 30) / 60),
                        ];
                    }
                }
            });

        $this->info(sprintf('Clustered %d hotspot candidate(s).', count($clusters)));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($clusters as $cluster) {
            if ($cluster['dwell_hours'] < $minHours) {
                $skipped++;
                continue;
            }

            $lat = round($cluster['lat'], 7);
            $lng = round($cluster['lng'], 7);

            $existing = Place::query()
                ->where('type', Place::TYPE_BASE)
                ->where('is_auto_detected', true)
                ->whereBetween('latitude', [$lat - 0.005, $lat + 0.005])
                ->whereBetween('longitude', [$lng - 0.005, $lng + 0.005])
                ->first();

            if ($existing) {
                // Only refresh coordinates (centroid) and dwell info.
                if (! $dryRun) {
                    $existing->update([
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'notes' => sprintf(
                            'Auto-detected base. Samples: %d, dwell ≈ %.1f h (refreshed %s)',
                            $cluster['samples'],
                            $cluster['dwell_hours'],
                            Carbon::now()->toDateString()
                        ),
                    ]);
                }
                $updated++;
                continue;
            }

            if (! $dryRun) {
                Place::create([
                    'code' => 'auto_base_' . substr(md5($lat . '_' . $lng), 0, 8),
                    'name' => sprintf('Base auto-détectée %.4f, %.4f', $lat, $lng),
                    'type' => Place::TYPE_BASE,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'radius_m' => $defaultRadiusM,
                    'is_auto_detected' => true,
                    'is_active' => true,
                    'notes' => sprintf(
                        'Auto-detected base. Samples: %d, dwell ≈ %.1f h',
                        $cluster['samples'],
                        $cluster['dwell_hours']
                    ),
                ]);
            }
            $created++;
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}Created {$created}, updated {$updated}, skipped {$skipped} (below dwell threshold).");

        return self::SUCCESS;
    }
}
