<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Models\Truck;
use App\Models\TruckStop;
use App\Services\GeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Per-stop drill-down: for the given trucks and date range, list every
 * TruckStop with its resolved location (Place if inside a geofence,
 * otherwise the nearest known Place + distance).
 *
 * Usage:
 *   php artisan stops:where --truck=6074 --truck=6066 --truck=6081 --from=2026-04-20 --to=2026-04-26
 *   php artisan stops:where --truck=6074  (last 30 days)
 */
class StopsWhere extends Command
{
    protected $signature = 'stops:where
        {--truck=* : Matricule pattern (partial match, repeatable)}
        {--from= : Start date (YYYY-MM-DD)}
        {--to= : End date (YYYY-MM-DD)}
        {--days=30 : Window if --from/--to are not set}';

    protected $description = 'List every truck stop in a window with its resolved location (geofence or nearest place)';

    public function handle(GeoService $geo): int
    {
        $patterns = (array) $this->option('truck');
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::now()->subDays((int) $this->option('days'))->startOfDay();
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $trucksQuery = Truck::query();
        if (! empty($patterns)) {
            $trucksQuery->where(function ($q) use ($patterns) {
                foreach ($patterns as $p) {
                    $q->orWhere('matricule', 'like', '%'.$p.'%');
                }
            });
        }
        $trucks = $trucksQuery->pluck('matricule', 'id');

        if ($trucks->isEmpty()) {
            $this->error('No trucks matched.');
            return self::FAILURE;
        }

        $stops = TruckStop::whereIn('truck_id', $trucks->keys())
            ->whereBetween('started_at', [$from, $to])
            ->orderBy('truck_id')
            ->orderBy('started_at')
            ->get();

        if ($stops->isEmpty()) {
            $this->warn('No stops in this window.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Stops for %s · window %s → %s',
            $trucks->values()->implode(', '),
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        ));

        $rows = $stops->map(function ($s) use ($geo, $trucks) {
            $place = $geo->nearestCoveringPlace((float) $s->latitude, (float) $s->longitude);
            $category = $place?->type ?? 'on_road';

            $extra = '';
            if (! $place) {
                $nearest = $geo->nearestPlace((float) $s->latitude, (float) $s->longitude, null, 50.0);
                if ($nearest) {
                    $km = $geo->haversineKm(
                        (float) $s->latitude, (float) $s->longitude,
                        (float) $nearest->latitude, (float) $nearest->longitude,
                    );
                    $extra = sprintf(' (à %.1f km de %s)', $km, $nearest->name);
                }
            }

            return [
                'truck' => $trucks[$s->truck_id] ?? '#'.$s->truck_id,
                'started' => Carbon::parse($s->started_at)->format('Y-m-d H:i'),
                'ended' => $s->ended_at ? Carbon::parse($s->ended_at)->format('Y-m-d H:i') : 'open',
                'duration' => $this->humanDuration((int) ($s->duration_seconds ?? 0)),
                'engine_off' => $s->ignition_was_off ? 'yes' : 'no',
                'category' => $this->categoryLabel($category),
                'location' => $place ? $place->name : 'Sur route'.$extra,
                'lat' => number_format((float) $s->latitude, 5),
                'lon' => number_format((float) $s->longitude, 5),
            ];
        });

        $this->table(
            ['camion', 'début', 'fin', 'durée', 'moteur OFF', 'catégorie', 'lieu', 'lat', 'lon'],
            $rows->map(fn ($r) => [
                $r['truck'], $r['started'], $r['ended'], $r['duration'],
                $r['engine_off'], $r['category'], $r['location'], $r['lat'], $r['lon'],
            ])->all(),
        );

        $byTruck = $stops->groupBy('truck_id')->map(function ($g) use ($trucks) {
            return [
                'truck' => $trucks[$g->first()->truck_id] ?? '?',
                'stops' => $g->count(),
                'engine_on' => $g->where('ignition_was_off', false)->count(),
                'engine_off' => $g->where('ignition_was_off', true)->count(),
                'total' => $this->humanDuration((int) $g->sum('duration_seconds')),
            ];
        })->values();

        $this->newLine();
        $this->info('Récapitulatif par camion');
        $this->table(
            ['camion', 'arrêts', 'moteur ON', 'moteur OFF', 'durée totale'],
            $byTruck->map(fn ($r) => [
                $r['truck'], $r['stops'], $r['engine_on'], $r['engine_off'], $r['total'],
            ])->all(),
        );

        return self::SUCCESS;
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
        if ($seconds <= 0) return '—';
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($d) $parts[] = $d.'d';
        if ($h) $parts[] = $h.'h';
        if ($m && ! $d) $parts[] = $m.'m';
        return $parts ? implode(' ', $parts) : '<1m';
    }
}
