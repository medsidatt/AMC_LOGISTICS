<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Models\TruckStop;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ListLocations extends Command
{
    protected $signature = 'locations:list
        {--days=30 : Days of truck-stop history to cluster}
        {--precision=3 : Decimal precision used to merge nearby stops (3 ≈ 110 m)}
        {--min-stops=1 : Minimum stops required for a cluster to appear}';

    protected $description = 'List all known locations: existing geofences (places) + clusters of raw truck-stop GPS points';

    public function handle(): int
    {
        $this->showPlaces();
        $this->newLine();
        $this->showStopClusters();

        return self::SUCCESS;
    }

    protected function showPlaces(): void
    {
        $places = Place::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'provider_id', 'latitude', 'longitude', 'radius_m', 'is_active']);

        $this->info("Geofences in `places` table ({$places->count()})");

        if ($places->isEmpty()) {
            $this->line('  (none)');
            return;
        }

        $this->table(
            ['id', 'code', 'name', 'type', 'provider_id', 'lat', 'lon', 'radius_m', 'active'],
            $places->map(fn ($p) => [
                $p->id,
                $p->code ?? '—',
                $p->name,
                $p->type,
                $p->provider_id ?? '—',
                number_format((float) $p->latitude, 5),
                number_format((float) $p->longitude, 5),
                $p->radius_m,
                $p->is_active ? 'yes' : 'no',
            ])->all(),
        );
    }

    protected function showStopClusters(): void
    {
        $days = (int) $this->option('days');
        $precision = (int) $this->option('precision');
        $minStops = (int) $this->option('min-stops');
        $since = Carbon::now()->subDays($days);

        $stops = TruckStop::with('truck')
            ->where('started_at', '>=', $since)
            ->get(['id', 'truck_id', 'place_id', 'latitude', 'longitude', 'started_at', 'ended_at', 'duration_seconds']);

        $this->info("Stop clusters from `truck_stops` (last {$days} days, precision {$precision} dp)");

        if ($stops->isEmpty()) {
            $this->line('  (no stops in window)');
            return;
        }

        $clusters = $stops->groupBy(fn ($s) => round((float) $s->latitude, $precision).'|'.round((float) $s->longitude, $precision));

        $rows = $clusters->map(function ($group, $key) {
            [$lat, $lon] = explode('|', $key);
            $trucks = $group->map(fn ($s) => $s->truck?->matricule ?? ('#'.$s->truck_id))->unique()->values();
            $totalDur = (int) $group->sum('duration_seconds');
            $linked = $group->whereNotNull('place_id')->count();

            return [
                'lat' => (float) $lat,
                'lon' => (float) $lon,
                'stops' => $group->count(),
                'trucks' => $trucks->count(),
                'truck_list' => $trucks->take(6)->implode(', ').($trucks->count() > 6 ? ', …' : ''),
                'total_park' => $this->humanDuration($totalDur),
                'linked_places' => $linked,
                'first_seen' => $group->min('started_at')?->format('Y-m-d H:i'),
                'last_seen' => $group->max('started_at')?->format('Y-m-d H:i'),
            ];
        })
        ->filter(fn ($r) => $r['stops'] >= $minStops)
        ->sortByDesc('stops')
        ->values();

        $this->table(
            ['lat', 'lon', 'stops', 'trucks', 'matricules', 'total park', 'place-linked', 'first', 'last'],
            $rows->map(fn ($r) => [
                number_format($r['lat'], 5),
                number_format($r['lon'], 5),
                $r['stops'],
                $r['trucks'],
                $r['truck_list'],
                $r['total_park'],
                $r['linked_places'],
                $r['first_seen'],
                $r['last_seen'],
            ])->all(),
        );

        $this->line("Total stops: {$stops->count()} · Clusters shown: {$rows->count()}");
    }

    protected function humanDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }
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
