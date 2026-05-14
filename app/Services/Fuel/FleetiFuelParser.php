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

        // Iterate sheets to find truck details. Pattern:
        //   Sheet N : "Rapport de carburant: 6078-TTA1 / FAW J6P-420" (header)
        //   Sheet N+1 : "Détail par dates" (daily breakdown)
        $currentTruck = null;
        foreach ($sheets as $sheetIdx => $sheet) {
            $first = $sheet[0][0] ?? null;
            if (! is_string($first)) {
                continue;
            }

            // Detect a per-truck header sheet
            if (stripos($first, 'Rapport de carburant') === 0 && stripos($first, 'Résumé') === false) {
                $currentTruck = $this->matchTruckFromHeader($first, $matriculeMap);
                continue;
            }

            // If "Détail par dates" sheet, parse rows with the most recent truck context
            if (str_contains(strtolower((string) $first), 'détail par dates') || stripos((string) $first, 'detail par date') !== false) {
                if (! $currentTruck) {
                    $invalid[] = ['line' => $sheetIdx, 'reason' => "Feuille 'Détail par dates' sans en-tête camion identifiable"];
                    continue;
                }

                // Headers occupy rows 0, 1, 2. Data starts at row 3.
                for ($i = 3; $i < count($sheet); $i++) {
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

                    $kmDay = $this->numericOrZero($row[1] ?? 0);
                    $consoCalcL = $this->numericOrNull($row[2] ?? null);
                    $consoCalcLPer100 = $this->numericOrNull($row[3] ?? null);
                    $volumeInitial = $this->numericOrZero($row[4] ?? 0);
                    $volumeFinal = $this->numericOrZero($row[5] ?? 0);
                    $consumed = $this->numericOrZero($row[6] ?? 0);
                    $consumedLPer100 = $this->numericOrNull($row[7] ?? null);
                    $refillsCount = (int) ($row[8] ?? 0);
                    $refillsVolume = $this->numericOrZero($row[9] ?? 0);
                    $drainsCount = (int) ($row[10] ?? 0);
                    $drainsVolume = $this->numericOrZero($row[11] ?? 0);

                    // Skip days with absolutely no activity
                    if ($kmDay == 0 && $consumed == 0 && $refillsCount == 0 && $refillsVolume == 0 && $drainsCount == 0 && $drainsVolume == 0) {
                        continue;
                    }

                    $totalRefilled += $refillsVolume;
                    $totalConsumed += $consumed;
                    $totalKm += $kmDay;
                    $trucksSeen[$currentTruck->id] = true;

                    $valid[] = [
                        'sheet' => $sheetIdx,
                        'row' => $i + 1,
                        'truck_id' => $currentTruck->id,
                        'truck_matricule' => $currentTruck->matricule,
                        'date' => $date->toDateString(),
                        'date_display' => $date->translatedFormat('d/m/Y'),
                        'kilometers' => round($kmDay, 2),
                        'volume_initial' => round($volumeInitial, 2),
                        'volume_final' => round($volumeFinal, 2),
                        'consumed' => round($consumed, 2),
                        'consumed_per_100km' => $consumedLPer100,
                        'refills_count' => $refillsCount,
                        'refills_volume' => round($refillsVolume, 2),
                        'drains_count' => $drainsCount,
                        'drains_volume' => round($drainsVolume, 2),
                    ];
                }
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
