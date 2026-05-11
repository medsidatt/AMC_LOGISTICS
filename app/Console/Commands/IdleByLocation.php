<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Models\Truck;
use App\Services\IdleHourlyReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Where do drivers idle?
 *
 * Idle = engine on AND speed < 3 km/h (defined by IdleHourlyReportService).
 * Each idle hour-bucket is mapped to a Place via centroid → nearestCoveringPlace,
 * then bucketed by place type (parking / provider / client / base / fuel / on-road).
 *
 * Output:
 *   1. Summary by category (parking, road, quarry, client base, …) + %
 *   2. Optional drill-down: top specific locations
 *   3. Optional per-truck breakdown
 */
class IdleByLocation extends Command
{
    protected $signature = 'idle:by-location
        {--days=30 : Days of telemetry history to analyse}
        {--truck= : Restrict to one matricule (optional)}
        {--top=15 : How many specific locations to list under the summary}
        {--per-truck : Also show idle distribution per matricule}';

    protected $description = 'Where do drivers idle? Bucketed by parking / road / quarry / client base with %';

    /** @var array<string,string> */
    private array $categoryLabels = [
        Place::TYPE_PARKING => 'Parking',
        Place::TYPE_PROVIDER_SITE => 'Carrière (provider)',
        Place::TYPE_CLIENT_SITE => 'Base client',
        Place::TYPE_BASE => 'Base / Hub',
        Place::TYPE_FUEL_STATION => 'Station-service',
        Place::TYPE_UNKNOWN => 'Zone connue (autre)',
        'on_road' => 'Sur route (hors géofence)',
    ];

    public function handle(IdleHourlyReportService $service): int
    {
        $days = (int) $this->option('days');
        $from = Carbon::now()->subDays($days);
        $to = Carbon::now();

        $trucksQuery = Truck::query();
        if ($matricule = $this->option('truck')) {
            $trucksQuery->where('matricule', $matricule);
        }
        $truckIds = $trucksQuery->pluck('id')->all();

        if (empty($truckIds)) {
            $this->error('No trucks found.');
            return self::FAILURE;
        }

        $this->info(sprintf('Analysing idle time for %d truck(s) over the last %d days…', count($truckIds), $days));

        $rows = $service->build($truckIds, $from, $to);

        if ($rows->isEmpty()) {
            $this->warn('No idle activity in this window.');
            return self::SUCCESS;
        }

        $placeTypes = Place::whereIn('id', $rows->pluck('place_id')->filter()->unique()->all())
            ->pluck('type', 'id');

        $rows = $rows->map(function ($r) use ($placeTypes) {
            $r['category'] = $r['place_id'] ? ($placeTypes[$r['place_id']] ?? Place::TYPE_UNKNOWN) : 'on_road';
            return $r;
        });

        $totalMinutes = (float) $rows->sum('idle_minutes');
        $totalHours = $totalMinutes / 60.0;

        $this->renderCategorySummary($rows, $totalMinutes);
        $this->newLine();
        $this->renderTopLocations($rows, $totalMinutes);

        if ($this->option('per-truck')) {
            $this->newLine();
            $this->renderPerTruck($rows);
        }

        $this->newLine();
        $this->line(sprintf(
            'Fleet idle total: %s h  ·  window %s → %s',
            number_format($totalHours, 2),
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        ));

        return self::SUCCESS;
    }

    private function renderCategorySummary(Collection $rows, float $totalMinutes): void
    {
        $this->info('Idle distribution by category');

        $byCategory = $rows->groupBy('category')->map(function ($g, $cat) use ($totalMinutes) {
            $minutes = (float) $g->sum('idle_minutes');
            return [
                'category' => $cat,
                'idle_minutes' => $minutes,
                'idle_hours' => $minutes / 60.0,
                'pct' => $totalMinutes > 0 ? ($minutes / $totalMinutes) * 100.0 : 0.0,
                'distinct_locations' => $g->pluck('location_label')->unique()->count(),
                'trucks' => $g->pluck('truck_matricule')->unique()->count(),
            ];
        })->sortByDesc('idle_minutes')->values();

        $this->table(
            ['Catégorie', 'idle (h)', '%', 'lieux distincts', 'camions'],
            $byCategory->map(fn ($r) => [
                $this->categoryLabels[$r['category']] ?? $r['category'],
                number_format($r['idle_hours'], 2),
                number_format($r['pct'], 1).' %',
                $r['distinct_locations'],
                $r['trucks'],
            ])->all(),
        );
    }

    private function renderTopLocations(Collection $rows, float $totalMinutes): void
    {
        $top = (int) $this->option('top');
        if ($top <= 0) {
            return;
        }

        $byLocation = $rows->groupBy('location_label')->map(function ($g, $label) use ($totalMinutes) {
            $minutes = (float) $g->sum('idle_minutes');
            return [
                'location' => $label,
                'category' => $g->first()['category'],
                'place_id' => $g->first()['place_id'],
                'idle_minutes' => $minutes,
                'pct' => $totalMinutes > 0 ? ($minutes / $totalMinutes) * 100.0 : 0.0,
                'trucks' => $g->pluck('truck_matricule')->unique()->count(),
            ];
        })->sortByDesc('idle_minutes')->values();

        $shown = $byLocation->take($top);
        $hidden = $byLocation->slice($top);

        $this->info(sprintf('Top %d lieux (détail)', min($top, $byLocation->count())));
        $this->table(
            ['#', 'lieu', 'catégorie', 'place_id', 'idle (h)', '%', 'camions'],
            $shown->map(fn ($r, $i) => [
                $i + 1,
                $this->truncate((string) $r['location'], 60),
                $this->categoryLabels[$r['category']] ?? $r['category'],
                $r['place_id'] ?? '—',
                number_format($r['idle_minutes'] / 60.0, 2),
                number_format($r['pct'], 1).' %',
                $r['trucks'],
            ])->all(),
        );

        if ($hidden->isNotEmpty()) {
            $restMinutes = (float) $hidden->sum('idle_minutes');
            $this->line(sprintf(
                '… plus %d autres lieux totalisant %.2f h (%.1f %%).',
                $hidden->count(),
                $restMinutes / 60.0,
                $totalMinutes > 0 ? ($restMinutes / $totalMinutes) * 100.0 : 0.0,
            ));
        }
    }

    private function renderPerTruck(Collection $rows): void
    {
        $this->info('Idle par camion (h par catégorie)');

        $categoryOrder = [
            Place::TYPE_PARKING,
            Place::TYPE_PROVIDER_SITE,
            Place::TYPE_CLIENT_SITE,
            Place::TYPE_BASE,
            Place::TYPE_FUEL_STATION,
            Place::TYPE_UNKNOWN,
            'on_road',
        ];

        $perTruck = $rows->groupBy('truck_matricule')->map(function ($g) {
            $totals = [];
            foreach ($g as $r) {
                $totals[$r['category']] = ($totals[$r['category']] ?? 0.0) + (float) $r['idle_minutes'];
            }
            $total = array_sum($totals);
            return [
                'totals' => $totals,
                'total_minutes' => $total,
            ];
        })->sortByDesc(fn ($v) => $v['total_minutes']);

        $headers = array_merge(
            ['matricule', 'total (h)'],
            array_map(fn ($c) => $this->categoryLabels[$c] ?? $c, $categoryOrder),
        );

        $tableRows = [];
        foreach ($perTruck as $matricule => $entry) {
            $row = [$matricule, number_format($entry['total_minutes'] / 60.0, 2)];
            foreach ($categoryOrder as $cat) {
                $minutes = $entry['totals'][$cat] ?? 0.0;
                $pct = $entry['total_minutes'] > 0 ? ($minutes / $entry['total_minutes']) * 100.0 : 0.0;
                $row[] = $minutes > 0
                    ? sprintf('%.2f (%.0f%%)', $minutes / 60.0, $pct)
                    : '—';
            }
            $tableRows[] = $row;
        }

        $this->table($headers, $tableRows);
    }

    private function truncate(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
    }
}
