<?php

namespace App\Services\Fuel;

use App\Models\Truck;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class FleetiFuelParser
{
    private const FRENCH_MONTH_ABBR = [
        'janv' => 1, 'févr' => 2, 'fevr' => 2, 'mars' => 3, 'avr' => 4,
        'mai' => 5, 'juin' => 6, 'juil' => 7, 'août' => 8, 'aout' => 8,
        'sept' => 9, 'oct' => 10, 'nov' => 11, 'déc' => 12, 'dec' => 12,
    ];

    /**
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     invalid: array<int, array<string, mixed>>,
     *     period: array{from: string|null, to: string|null},
     *     totals: array{count_rows: int, count_trucks: int, litres_refilled: float, litres_consumed: float, km: float},
     * }
     */
    public function parse(string $path, ?string $readerType = null): array
    {
        $sheets = Excel::toArray([], $path, null, $readerType);

        $valid = [];
        $invalid = [];
        $period = ['from' => null, 'to' => null];
        $totalRefilled = 0.0;
        $totalConsumed = 0.0;
        $totalKm = 0.0;
        $trucksSeen = [];

        $trucks = Truck::where('is_active', true)->get(['id', 'matricule']);
        $matriculeMap = $trucks->mapWithKeys(fn ($t) => [
            $this->normalize($t->matricule) => $t,
        ]);

        // Detect period from any sheet's header
        foreach ($sheets as $sheet) {
            foreach (array_slice($sheet, 0, 3) as $row) {
                $first = $row[0] ?? '';
                if (is_string($first) && stripos($first, 'période') !== false) {
                    [$from, $to] = $this->parsePeriod($first);
                    if ($from && $to) {
                        $period = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
                        break 2;
                    }
                }
            }
        }

        // Fleeti workbooks interleave, per truck, a header/chart sheet followed by one or more
        // "Détail par dates" sheets. The current exports split into two files — "Volume de
        // carburant 2.0: PLATE" (refuel + consumption) and "Carburant: PLATE" (tank telemetry) —
        // while the retired single export used "Rapport de carburant: PLATE". All three are handled
        // by (a) recognising any of those header titles and (b) reading each detail sheet's own
        // layout. The truck carries over from the most recent header sheet.
        $currentTruck = null;
        $currentFormat = 'rapport';
        foreach ($sheets as $sheetIdx => $sheet) {
            $first = $sheet[0][0] ?? null;
            if (! is_string($first)) {
                continue;
            }

            if ($this->isTruckHeader($first)) {
                $currentTruck = $this->matchTruckFromHeader($first, $matriculeMap);
                $currentFormat = $this->detectFormat($first);
                continue;
            }

            if (! $this->isDetailSheet($first)) {
                continue;
            }

            if (! $currentTruck) {
                $invalid[] = ['line' => $sheetIdx, 'reason' => "Feuille 'Détail par dates' sans en-tête camion identifiable"];
                continue;
            }

            foreach ($this->parseDetailSheet($sheet, $currentTruck, $currentFormat) as $entry) {
                $totalRefilled += $entry['refills_volume'] ?? 0;
                $totalConsumed += $entry['consumed'] ?? 0;
                $totalKm += $entry['kilometers'] ?? 0;
                $trucksSeen[$currentTruck->id] = true;
                $valid[] = $entry;
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'period' => $period,
            'totals' => [
                'count_rows' => count($valid),
                'count_trucks' => count($trucksSeen),
                'litres_refilled' => round($totalRefilled, 2),
                'litres_consumed' => round($totalConsumed, 2),
                'km' => round($totalKm, 2),
            ],
        ];
    }

    /** A per-truck header/chart sheet ("Volume de carburant 2.0: …", "Carburant: …", legacy "Rapport …"). */
    private function isTruckHeader(string $first): bool
    {
        if ($this->looksLikeSummary($first)) {
            return false;
        }

        return stripos($first, 'Rapport de carburant') === 0
            || stripos($first, 'Volume de carburant') === 0
            || stripos($first, 'Carburant') === 0;
    }

    /**
     * Which export produced this workbook — decides deterministic field ownership (V2):
     *   volume2   → owns consumption + refuel (km, consumed, refills)
     *   carburant → owns tank telemetry only (volume_initial/final, drains)
     *   rapport   → the retired single file owns everything (sole source)
     * "Volume de carburant" is checked before "Carburant" because it contains that word.
     */
    private function detectFormat(string $first): string
    {
        $f = strtolower($first);
        if (str_contains($f, 'volume de carburant')) {
            return 'volume2';
        }
        if (str_contains($f, 'rapport de carburant')) {
            return 'rapport';
        }

        return 'carburant';
    }

    /**
     * The FleetiDailyRecord columns a given export is authoritative for. Volume 2.0 and Carburant
     * own disjoint sets, so importing both (in any order) is deterministic — neither overwrites the
     * other's columns. The legacy single "Rapport" owns all columns.
     *
     * @return array<int, string>
     */
    private function ownedFields(string $format): array
    {
        $consumption = ['kilometers', 'consumed', 'consumed_per_100km', 'refills_count', 'refills_volume'];
        $tank = ['volume_initial', 'volume_final', 'drains_count', 'drains_volume'];

        return match ($format) {
            'volume2' => $consumption,
            'carburant' => $tank,
            default => array_merge($consumption, $tank),
        };
    }

    private function looksLikeSummary(string $first): bool
    {
        $f = strtolower($first);

        return str_contains($f, 'résumé') || str_contains($f, 'resume');
    }

    private function isDetailSheet(string $first): bool
    {
        $f = strtolower($first);

        return str_contains($f, 'détail par dates') || str_contains($f, 'detail par date');
    }

    /**
     * Parse one "Détail par dates" sheet into canonical daily rows. The column layout is read from
     * the sheet header (6-col refuel vs 12-col tank), while WHICH columns get persisted is decided
     * by the export `$format` via `_owned` — so Volume 2.0 (consumption) and Carburant (tank) write
     * disjoint columns and the merged result is import-order independent (§4 ownership; V2 fix).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseDetailSheet(array $sheet, Truck $truck, string $format): array
    {
        [$dataStart, $hasTank] = $this->detectLayout($sheet);
        $owned = $this->ownedFields($format);
        $rows = [];

        for ($i = $dataStart; $i < count($sheet); $i++) {
            $row = $sheet[$i];
            $dateRaw = $row[0] ?? null;
            if (! is_string($dateRaw) || $dateRaw === '') {
                continue;
            }
            if (stripos($dateRaw, 'total') !== false || stripos($dateRaw, 'date') !== false) {
                continue;
            }

            $date = $this->parseDayDate($dateRaw);
            if (! $date) {
                continue;
            }

            $km = $this->numericOrZero($row[1] ?? 0);

            if ($hasTank) {
                $volumeInitial = $this->numericOrZero($row[4] ?? 0);
                $volumeFinal = $this->numericOrZero($row[5] ?? 0);
                $consumed = $this->numericOrZero($row[6] ?? 0);
                $consumedPer100 = $this->numericOrNull($row[7] ?? null);
                $refillsCount = (int) ($row[8] ?? 0);
                $refillsVolume = $this->numericOrZero($row[9] ?? 0);
                $drainsCount = (int) ($row[10] ?? 0);
                $drainsVolume = $this->numericOrZero($row[11] ?? 0);
            } else {
                $refillsCount = (int) ($row[2] ?? 0);
                $refillsVolume = $this->numericOrZero($row[3] ?? 0);
                $consumed = $this->numericOrZero($row[4] ?? 0);
                $consumedPer100 = $this->numericOrNull($row[5] ?? null);
                $drainsCount = 0;
                $drainsVolume = 0.0;
            }

            // Skip days with absolutely no activity.
            if ($km == 0 && $consumed == 0 && $refillsCount == 0 && $refillsVolume == 0 && $drainsCount == 0 && $drainsVolume == 0) {
                continue;
            }

            // All parsed values are attached for preview/totals display; `_owned` restricts which
            // ones are actually persisted (see FuelImportController::commitFleeti).
            $entry = [
                'truck_id' => $truck->id,
                'truck_matricule' => $truck->matricule,
                'date' => $date->toDateString(),
                'date_display' => $date->translatedFormat('d/m/Y'),
                'kilometers' => round($km, 2),
                'consumed' => round($consumed, 2),
                'consumed_per_100km' => $consumedPer100,
                'refills_count' => $refillsCount,
                'refills_volume' => round($refillsVolume, 2),
                '_owned' => $owned,
            ];

            if ($hasTank) {
                $entry['volume_initial'] = round($volumeInitial, 2);
                $entry['volume_final'] = round($volumeFinal, 2);
                $entry['drains_count'] = $drainsCount;
                $entry['drains_volume'] = round($drainsVolume, 2);
            }

            $rows[] = $entry;
        }

        return $rows;
    }

    /**
     * Read a detail sheet's header to locate the first data row and whether it is a tank layout.
     *
     * @return array{0:int, 1:bool} [dataStartRowIndex, hasTankColumns]
     */
    private function detectLayout(array $sheet): array
    {
        $lastDateRow = 1;
        $hasTank = false;

        foreach (array_slice($sheet, 0, 4, true) as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                if (! is_string($cell)) {
                    continue;
                }
                if (trim($cell) === 'Date') {
                    $lastDateRow = max($lastDateRow, (int) $idx);
                }
                if (stripos($cell, 'Volume initial') !== false) {
                    $hasTank = true;
                }
            }
        }

        return [$lastDateRow + 1, $hasTank];
    }

    private function matchTruckFromHeader(string $text, $matriculeMap): ?Truck
    {
        if (! preg_match('/(\d{4})\s?-?T\s?T\s?A\s?1?/i', $text, $m)) {
            return null;
        }
        $candidate = $this->normalize($m[0]);
        if (! str_ends_with($candidate, 'TTA1')) {
            $candidate = preg_replace('/TTA?$/', 'TTA1', $candidate);
        }
        return $matriculeMap->get($candidate);
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function parsePeriod(string $text): array
    {
        if (! preg_match('/(\d{1,2})\s+([a-zéûôA-Z\.]+)\s+(\d{4}).*?(\d{1,2})\s+([a-zéûôA-Z\.]+)\s+(\d{4})/u', $text, $m)) {
            return [null, null];
        }
        $from = $this->buildDate($m[1], $m[2], $m[3]);
        $to = $this->buildDate($m[4], $m[5], $m[6]);
        return [$from, $to];
    }

    private function parseDayDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if (! preg_match('/^(\d{1,2})\s+([a-zéûôA-Z\.]+)\s+(\d{4})$/u', $raw, $m)) {
            return null;
        }
        return $this->buildDate($m[1], $m[2], $m[3]);
    }

    private function buildDate(string $day, string $monthRaw, string $year): ?Carbon
    {
        $key = strtolower(rtrim(trim($monthRaw), '.'));
        $month = null;
        foreach (self::FRENCH_MONTH_ABBR as $abbr => $num) {
            if (str_starts_with($key, $abbr)) {
                $month = $num;
                break;
            }
        }
        if (! $month) {
            return null;
        }
        try {
            return Carbon::create((int) $year, $month, (int) $day);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalize(string $raw): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $raw));
    }

    private function numericOrNull($v): ?float
    {
        if ($v === null || $v === '' || (is_string($v) && trim($v) === '—') || (is_string($v) && str_contains($v, '—'))) {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }
        return (float) $v;
    }

    private function numericOrZero($v): float
    {
        return $this->numericOrNull($v) ?? 0.0;
    }
}
