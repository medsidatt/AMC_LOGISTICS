<?php

namespace App\Console\Commands;

use App\Models\Place;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * One-shot analyser for a Fleeti "Rapport des trajets" export.
 *
 * Reads every per-truck sheet, derives the stops that occur between
 * consecutive trips (column K already carries the idle duration in days),
 * clusters them by location, and classifies each cluster into:
 *
 *   parking, quarry, warehouse, fuel_station, unknown
 *
 * The result is written to an Excel workbook with several sheets:
 *   - "Lieux identifiés"  : one row per cluster with its classification + stats
 *   - "Arrêts autorisés"  : every stop that fell at a parking/quarry/warehouse/fuel-station
 *   - "Arrêts non autorisés" : every stop at an unknown location
 */
class AnalyzeFleetStops extends Command
{
    protected $signature = 'logistics:analyze-fleet-stops
        {input : Path to the Fleeti Rapport_des_trajets xlsx file}
        {--output= : Path for the output xlsx (defaults to storage/app/imports/fleet_stops_classified.xlsx)}
        {--min-duration=10 : Minimum stop duration in minutes to keep}
        {--cluster-radius=200 : Cluster radius in metres}
        {--seed-places : Wipe the places table and replace it with the classified clusters}';

    protected $description = 'Read a Fleeti trajets export, derive stops, cluster + classify them.';

    /** @var array<int, array<string, mixed>> */
    private array $stops = [];

    /** @var array<int, array<string, mixed>> */
    private array $clusters = [];

    public function handle(): int
    {
        $input = $this->argument('input');
        if (! is_file($input)) {
            $this->error("Input file not found: {$input}");
            return self::FAILURE;
        }

        $minMinutes = (float) $this->option('min-duration');
        $radiusM = (float) $this->option('cluster-radius');
        $output = $this->option('output') ?: storage_path('app/imports/fleet_stops_classified.xlsx');
        @mkdir(dirname($output), 0777, true);

        $this->info("Reading {$input} …");
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $ss = $reader->load($input);

        foreach ($ss->getSheetNames() as $idx => $name) {
            // Skip the summary sheets ("- 2" suffix) and the overall résumé.
            if ($idx === 0) continue;
            if (str_ends_with($name, ' - 2')) continue;

            $matricule = trim(preg_replace('/\s+/', ' ', $name));
            // Take the first whitespace-delimited token as the matricule.
            $matricule = explode(' ', $matricule)[0];

            $this->parseTruckSheet($ss->getSheet($idx), $matricule, $minMinutes);
        }

        $this->info(sprintf('Parsed %d stops from %d truck sheet(s).', count($this->stops), ($ss->getSheetCount() - 1) / 2));

        $this->buildClusters($radiusM);
        $this->classifyClusters();
        $this->assignClassificationToStops();

        $allowedTypes = ['parking', 'quarry', 'warehouse', 'fuel_station'];
        $byType = [
            'parking' => [],
            'quarry' => [],
            'warehouse' => [],
            'fuel_station' => [],
            'unknown' => [],
        ];
        foreach ($this->stops as $s) {
            $byType[$s['classification']][] = $s;
        }

        $this->info(sprintf(
            'Clusters: %d (allowed: %d, unknown: %d). Stops: parking=%d, quarry=%d, warehouse=%d, fuel=%d, unknown=%d.',
            count($this->clusters),
            count(array_filter($this->clusters, fn ($c) => in_array($c['classification'], $allowedTypes, true))),
            count(array_filter($this->clusters, fn ($c) => $c['classification'] === 'unknown')),
            count($byType['parking']),
            count($byType['quarry']),
            count($byType['warehouse']),
            count($byType['fuel_station']),
            count($byType['unknown']),
        ));

        $this->writeOutput($output, $byType);
        $this->info("Wrote: {$output}");

        if ($this->option('seed-places')) {
            $this->seedPlaces();
        }

        return self::SUCCESS;
    }

    /**
     * Wipe the places table and insert one row per classified cluster.
     *
     * `truck_stops.place_id` and `trip_segments.origin/destination_place_id`
     * use ON DELETE SET NULL, so removing the old rows just nulls those
     * foreign keys — no cascading destruction of stop or segment history.
     * The next run of `places:detect-hubs` + `logistics:rebuild-trip-segments`
     * (or the existing classifier) will re-attach them to the new places.
     */
    private function seedPlaces(): void
    {
        $typeMap = [
            'parking' => Place::TYPE_PARKING,
            'quarry' => Place::TYPE_PROVIDER_SITE,
            'warehouse' => Place::TYPE_CLIENT_SITE,
            'fuel_station' => Place::TYPE_FUEL_STATION,
        ];

        $toInsert = [];
        $now = now();
        foreach ($this->clusters as $c) {
            if (! isset($typeMap[$c['classification']])) continue;

            $toInsert[] = [
                'name' => $c['label'] !== '' ? $c['label'] : sprintf('%.4f, %.4f', $c['lat'], $c['lng']),
                'type' => $typeMap[$c['classification']],
                'latitude' => round($c['lat'], 7),
                'longitude' => round($c['lng'], 7),
                'radius_m' => 250,
                'is_auto_detected' => true,
                'is_active' => true,
                'notes' => sprintf(
                    'Importé depuis Fleeti — %d arrêts (médiane %s min) sur %d camion(s).',
                    $c['count'],
                    round($c['median_minutes'], 1),
                    count($c['trucks']),
                ),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! $this->confirm(sprintf(
            'About to DELETE all %d existing places and INSERT %d fresh ones. Proceed?',
            DB::table('places')->count(),
            count($toInsert),
        ), false)) {
            $this->warn('Aborted — places table left untouched.');
            return;
        }

        DB::transaction(function () use ($toInsert) {
            DB::table('places')->delete();
            foreach (array_chunk($toInsert, 200) as $chunk) {
                DB::table('places')->insert($chunk);
            }
        });

        $this->info(sprintf('Replaced places table with %d row(s).', count($toInsert)));
    }

    private function parseTruckSheet($sheet, string $matricule, float $minMinutes): void
    {
        $lastRow = $sheet->getHighestRow();
        $currentDate = null;

        // Header rows are 1..6, data starts at 7.
        for ($r = 7; $r <= $lastRow; $r++) {
            $colA = (string) $sheet->getCell('A' . $r)->getValue();
            $colD = (string) $sheet->getCell('D' . $r)->getValue();

            // Day-header row: "DD MMM. YYYY (XXX) : NN"  — only column A has content
            if ($colA && $colD === '' && preg_match('/^(\d{1,2})\s+([A-Za-zéûîàâ.]+)\s+(\d{4})/u', $colA, $m)) {
                $currentDate = $this->parseFrenchDate($m[1], $m[2], $m[3]);
                continue;
            }

            // Skip the "Au total :" footer row
            if (str_starts_with($colA, 'Au total')) continue;

            // Trip rows have both A and D filled with "HH:MM:SS - ..." prefix
            if (! preg_match('/^(\d{2}:\d{2}:\d{2})\s*-\s*(.*)$/', $colA, $depMatch)) continue;
            if (! preg_match('/^(\d{2}:\d{2}:\d{2})\s*-\s*(.*)$/', $colD, $arrMatch)) continue;
            if (! $currentDate) continue;

            $arrLat = $this->numeric($sheet->getCell('E' . $r)->getValue());
            $arrLng = $this->numeric($sheet->getCell('F' . $r)->getValue());
            $idle = $this->numeric($sheet->getCell('K' . $r)->getValue());

            if ($arrLat === null || $arrLng === null) continue;

            $arrTime = $arrMatch[1];
            $arrAddress = trim($arrMatch[2]);
            $arrivalAt = $currentDate . ' ' . $arrTime;

            // Idle is stored in fractions of a day in Fleeti exports.
            $idleMinutes = $idle !== null ? round((float) $idle * 1440, 1) : 0.0;

            if ($idleMinutes < $minMinutes) continue;

            $this->stops[] = [
                'truck' => $matricule,
                'arrival_at' => $arrivalAt,
                'departure_at' => $this->addMinutes($arrivalAt, $idleMinutes),
                'duration_minutes' => $idleMinutes,
                'latitude' => $arrLat,
                'longitude' => $arrLng,
                'address' => $arrAddress,
            ];
        }
    }

    private function buildClusters(float $radiusM): void
    {
        usort($this->stops, fn ($a, $b) => $a['latitude'] <=> $b['latitude'] ?: $a['longitude'] <=> $b['longitude']);

        $clusters = [];

        foreach ($this->stops as $idx => &$s) {
            $found = null;
            foreach ($clusters as $ci => $c) {
                if ($this->haversineMeters($s['latitude'], $s['longitude'], $c['lat'], $c['lng']) <= $radiusM) {
                    $found = $ci;
                    break;
                }
            }

            if ($found === null) {
                $clusters[] = [
                    'lat' => $s['latitude'],
                    'lng' => $s['longitude'],
                    'count' => 1,
                    'total_minutes' => $s['duration_minutes'],
                    'trucks' => [$s['truck'] => true],
                    'addresses' => [$s['address'] => 1],
                    'stop_idxs' => [$idx],
                    'durations' => [$s['duration_minutes']],
                ];
            } else {
                $c =& $clusters[$found];
                // Running average of the centre.
                $c['lat'] = (($c['lat'] * $c['count']) + $s['latitude']) / ($c['count'] + 1);
                $c['lng'] = (($c['lng'] * $c['count']) + $s['longitude']) / ($c['count'] + 1);
                $c['count']++;
                $c['total_minutes'] += $s['duration_minutes'];
                $c['trucks'][$s['truck']] = true;
                $c['addresses'][$s['address']] = ($c['addresses'][$s['address']] ?? 0) + 1;
                $c['stop_idxs'][] = $idx;
                $c['durations'][] = $s['duration_minutes'];
                unset($c);
            }
        }

        $this->clusters = $clusters;
    }

    private function classifyClusters(): void
    {
        foreach ($this->clusters as &$c) {
            arsort($c['addresses']);
            $topAddress = (string) array_key_first($c['addresses']);
            sort($c['durations']);
            $median = $c['durations'][(int) floor((count($c['durations']) - 1) / 2)] ?? 0;

            $c['top_address'] = $topAddress;
            $c['median_minutes'] = $median;
            $c['classification'] = $this->classifyOne($c, $topAddress, $median);
            $c['label'] = $this->labelFor($c, $topAddress);
        }
        unset($c);
    }

    /**
     * Rule-based classifier. Order matters — more specific tests first.
     */
    private function classifyOne(array $c, string $address, float $median): string
    {
        $addrLower = mb_strtolower($address);

        // Fuel stations: explicit brand keywords.
        $fuelKeywords = ['edk', 'total', 'shell', 'star oil', 'oilibya', 'station service'];
        foreach ($fuelKeywords as $kw) {
            if (str_contains($addrLower, $kw)) return 'fuel_station';
        }

        // Parking: long-duration cluster used by multiple trucks repeatedly.
        // Trucks typically idle 8h+ at base; require median >= 4h to keep
        // mid-day rest stops out.
        if ($median >= 240 && count($c['trucks']) >= 2 && $c['count'] >= 10) {
            return 'parking';
        }

        // Warehouse / client zone: Rosso area + several visits.
        // Rosso clients sit roughly at lat 16.4-16.6, lng -15.7 to -15.9.
        if ($c['lat'] >= 16.3 && $c['lat'] <= 16.7 && $c['lng'] >= -16.0 && $c['lng'] <= -15.5
            && $c['count'] >= 3 && $median >= 30) {
            return 'warehouse';
        }
        // Also: keyword "rosso" in the address with a meaningful stop.
        if (str_contains($addrLower, 'rosso') && $c['count'] >= 3 && $median >= 30) {
            return 'warehouse';
        }

        // Quarry: Thiès / Diack zone with regular medium-length stops.
        // Quarries sit roughly at lat 14.6-15.1, around the loading area.
        $quarryKeywords = ['diack', 'carrière', 'carriere', 'cse', 'granulat', 'sococim'];
        $matchesQuarryKw = false;
        foreach ($quarryKeywords as $kw) {
            if (str_contains($addrLower, $kw)) { $matchesQuarryKw = true; break; }
        }
        if ($matchesQuarryKw && $c['count'] >= 2 && $median >= 15) return 'quarry';

        if ($c['lat'] >= 14.6 && $c['lat'] <= 15.2 && $c['lng'] >= -17.0 && $c['lng'] <= -16.3
            && $c['count'] >= 5 && $median >= 20 && $median <= 180) {
            return 'quarry';
        }

        return 'unknown';
    }

    private function labelFor(array $c, string $address): string
    {
        // Pull a short label from the address.
        $shortAddr = preg_replace('/^\[[^\]]+\]\s*/', '', $address);
        $shortAddr = trim(explode(',', $shortAddr)[0]);
        if ($shortAddr === '' || mb_strlen($shortAddr) < 3) {
            $shortAddr = sprintf('%.4f, %.4f', $c['lat'], $c['lng']);
        }
        return $shortAddr;
    }

    private function assignClassificationToStops(): void
    {
        foreach ($this->clusters as $c) {
            foreach ($c['stop_idxs'] as $idx) {
                $this->stops[$idx]['classification'] = $c['classification'];
                $this->stops[$idx]['place_label'] = $c['label'];
                $this->stops[$idx]['cluster_lat'] = round($c['lat'], 6);
                $this->stops[$idx]['cluster_lng'] = round($c['lng'], 6);
            }
        }
    }

    private function writeOutput(string $path, array $stopsByType): void
    {
        $ss = new Spreadsheet();
        $ss->removeSheetByIndex(0);

        $typeMeta = [
            'parking' => ['#22c55e', 'Parking'],
            'quarry' => ['#3b82f6', 'Carrière (fournisseur)'],
            'warehouse' => ['#a855f7', 'Entrepôt (client)'],
            'fuel_station' => ['#f59e0b', 'Station carburant'],
            'unknown' => ['#9ca3af', 'Inconnu'],
        ];

        // Sheet 1: identified places (all classifications, sorted by count).
        $sheet = $ss->createSheet();
        $sheet->setTitle('Lieux identifiés');
        $headers = ['Classification', 'Étiquette', 'Latitude', 'Longitude', 'Nb arrêts', 'Camions distincts', 'Durée médiane (min)', 'Durée totale (h)', 'Adresse principale', 'Carte'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 'A1:J1');

        usort($this->clusters, fn ($a, $b) => $b['count'] <=> $a['count']);
        $row = 2;
        foreach ($this->clusters as $c) {
            $sheet->setCellValue("A{$row}", $typeMeta[$c['classification']][1] ?? $c['classification']);
            $sheet->setCellValue("B{$row}", $c['label']);
            $sheet->setCellValue("C{$row}", round($c['lat'], 6));
            $sheet->setCellValue("D{$row}", round($c['lng'], 6));
            $sheet->setCellValue("E{$row}", $c['count']);
            $sheet->setCellValue("F{$row}", count($c['trucks']));
            $sheet->setCellValue("G{$row}", round($c['median_minutes'], 1));
            $sheet->setCellValue("H{$row}", round($c['total_minutes'] / 60, 1));
            $sheet->setCellValue("I{$row}", $c['top_address']);

            $mapUrl = sprintf('https://www.google.com/maps?q=%.6f,%.6f', $c['lat'], $c['lng']);
            $sheet->setCellValue("J{$row}", 'Ouvrir sur Google Maps');
            $sheet->getCell("J{$row}")->getHyperlink()->setUrl($mapUrl);
            $sheet->getStyle("J{$row}")->getFont()->getColor()->setARGB('FF1d4ed8');
            $sheet->getStyle("J{$row}")->getFont()->setUnderline(true);

            $hex = ltrim($typeMeta[$c['classification']][0] ?? '#9ca3af', '#');
            $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($hex);
            $sheet->getStyle("A{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $row++;
        }
        foreach (range('A', 'J') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        // One sheet per type — each holds only that type's stops.
        $this->writeStopsSheet($ss, 'Parkings', $stopsByType['parking'] ?? []);
        $this->writeStopsSheet($ss, 'Carrières', $stopsByType['quarry'] ?? []);
        $this->writeStopsSheet($ss, 'Entrepôts', $stopsByType['warehouse'] ?? []);
        $this->writeStopsSheet($ss, 'Stations carburant', $stopsByType['fuel_station'] ?? []);
        $this->writeStopsSheet($ss, 'Non autorisés', $stopsByType['unknown'] ?? []);

        $writer = new XlsxWriter($ss);
        $writer->save($path);
    }

    private function writeStopsSheet(Spreadsheet $ss, string $title, array $stops): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle($title);
        $headers = ['Camion', 'Étiquette du lieu', 'Arrivée', 'Départ', 'Durée (min)', 'Latitude', 'Longitude', 'Adresse', 'Carte'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 'A1:I1');

        usort($stops, fn ($a, $b) => strcmp($a['arrival_at'], $b['arrival_at']));

        $row = 2;
        foreach ($stops as $s) {
            $sheet->setCellValue("A{$row}", $s['truck']);
            $sheet->setCellValue("B{$row}", $s['place_label'] ?? '');
            $sheet->setCellValue("C{$row}", $s['arrival_at']);
            $sheet->setCellValue("D{$row}", $s['departure_at']);
            $sheet->setCellValue("E{$row}", round($s['duration_minutes'], 1));
            $sheet->setCellValue("F{$row}", $s['latitude']);
            $sheet->setCellValue("G{$row}", $s['longitude']);
            $sheet->setCellValue("H{$row}", $s['address']);

            $mapUrl = sprintf('https://www.google.com/maps?q=%.6f,%.6f', $s['latitude'], $s['longitude']);
            $sheet->setCellValue("I{$row}", 'Voir sur la carte');
            $sheet->getCell("I{$row}")->getHyperlink()->setUrl($mapUrl);
            $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FF1d4ed8');
            $sheet->getStyle("I{$row}")->getFont()->setUnderline(true);
            $row++;
        }
        foreach (range('A', 'I') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1f2937');
        $sheet->getStyle($range)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function parseFrenchDate(string $day, string $monthFr, string $year): ?string
    {
        $months = [
            'janv' => '01', 'févr' => '02', 'fevr' => '02', 'mars' => '03',
            'avr' => '04', 'mai' => '05', 'juin' => '06', 'juil' => '07',
            'août' => '08', 'aout' => '08', 'sept' => '09', 'oct' => '10',
            'nov' => '11', 'déc' => '12', 'dec' => '12',
        ];
        $key = mb_strtolower(rtrim($monthFr, '.'));
        foreach ($months as $prefix => $m) {
            if (str_starts_with($key, $prefix)) {
                return sprintf('%04d-%s-%02d', (int) $year, $m, (int) $day);
            }
        }
        return null;
    }

    private function addMinutes(string $datetime, float $minutes): string
    {
        $ts = strtotime($datetime);
        if (! $ts) return $datetime;
        return date('Y-m-d H:i:s', $ts + (int) round($minutes * 60));
    }

    private function numeric(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        if (! is_numeric($v)) return null;
        return (float) $v;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6_371_000.0;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lng2 - $lng1);
        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
        return 2 * $R * asin(min(1.0, sqrt($a)));
    }
}
