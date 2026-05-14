<?php

namespace App\Services\Fuel;

use App\Models\Driver;
use App\Models\Truck;
use Carbon\Carbon;

class EdkFuelParser
{
    private const FRENCH_MONTHS = [
        'jan' => '01', 'fev' => '02', 'mar' => '03', 'avr' => '04',
        'mai' => '05', 'juin' => '06', 'juil' => '07', 'aou' => '08',
        'sep' => '09', 'oct' => '10', 'nov' => '11', 'dec' => '12',
    ];

    /**
     * Parse a CSV uploaded file and return preview rows.
     *
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     invalid: array<int, array<string, mixed>>,
     *     totals: array{count_valid: int, count_invalid: int, total_litres: float, total_fcfa: float},
     * }
     */
    public function parse(string $contents, float $pricePerLitre): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents);
        $valid = [];
        $invalid = [];
        $totalLitres = 0.0;
        $totalFcfa = 0.0;

        $trucks = Truck::where('is_active', true)->get(['id', 'matricule']);
        $matriculeMap = $trucks->mapWithKeys(fn ($t) => [
            $this->normalizeMatricule($t->matricule) => $t,
        ]);
        $drivers = Driver::where('is_active', true)->get(['id', 'name']);

        foreach ($lines as $idx => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Skip header
            if (stripos($line, 'ID Transaction') !== false) {
                continue;
            }
            // Skip footer
            if (stripos($line, 'Montant Total') !== false) {
                continue;
            }

            $cols = array_map('trim', explode(';', $line));
            if (count($cols) < 6) {
                continue;
            }

            [, $txnId, $dateRaw, $montantRaw, $carte, $porteur] = $cols;

            $date = $this->parseDate($dateRaw);
            $montant = (float) preg_replace('/[^0-9.]/', '', $montantRaw);

            if (! $date || $montant <= 0) {
                $invalid[] = [
                    'line' => $idx + 1,
                    'reason' => ! $date ? 'Date invalide' : 'Montant invalide',
                    'raw' => $line,
                ];
                continue;
            }

            $matched = $this->matchMatricule($porteur, $matriculeMap);
            if (! $matched) {
                $invalid[] = [
                    'line' => $idx + 1,
                    'reason' => 'Camion non identifié dans le porteur',
                    'porteur' => $porteur,
                    'date' => $date->toDateString(),
                    'montant' => $montant,
                ];
                continue;
            }

            $litres = round($montant / $pricePerLitre, 2);
            $driver = $this->matchDriver($porteur, $drivers);

            $totalLitres += $litres;
            $totalFcfa += $montant;

            $valid[] = [
                'line' => $idx + 1,
                'txn_id' => $txnId,
                'date' => $date->toDateTimeString(),
                'date_display' => $date->format('d/m/Y H:i'),
                'montant' => $montant,
                'litres' => $litres,
                'carte' => $carte,
                'porteur' => $porteur,
                'truck_id' => $matched->id,
                'truck_matricule' => $matched->matricule,
                'driver_id' => $driver?->id,
                'driver_name' => $driver?->name,
            ];
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'totals' => [
                'count_valid' => count($valid),
                'count_invalid' => count($invalid),
                'total_litres' => round($totalLitres, 2),
                'total_fcfa' => round($totalFcfa, 2),
            ],
        ];
    }

    private function parseDate(string $raw): ?Carbon
    {
        // Format: "13-Mai-2026  22:12:23" (possibly multiple spaces)
        $raw = preg_replace('/\s+/', ' ', trim($raw));
        if (! preg_match('/^(\d{1,2})-([A-Za-zÀ-ÿ]{3,4})-(\d{4})\s+(\d{1,2}:\d{2}(:\d{2})?)$/u', $raw, $m)) {
            return null;
        }

        $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $monthKey = strtolower(substr($this->stripAccents($m[2]), 0, 4));
        // Normalize "juil" → keep 4 chars; "juin" → 4 chars; others = 3 chars
        $monthKeyShort = substr($monthKey, 0, 3);
        $month = self::FRENCH_MONTHS[$monthKey] ?? self::FRENCH_MONTHS[$monthKeyShort] ?? null;
        if (! $month) {
            return null;
        }
        $year = $m[3];
        $time = $m[4];
        if (substr_count($time, ':') === 1) {
            $time .= ':00';
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', "$year-$month-$day $time");
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function stripAccents(string $s): string
    {
        return strtr($s, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E',
            'À' => 'A',
        ]);
    }

    private function normalizeMatricule(string $raw): string
    {
        return strtoupper(preg_replace('/\s+/', '', $raw));
    }

    private function matchMatricule(string $porteur, $matriculeMap): ?Truck
    {
        // Find all candidate 4-digit + TTA1 sequences (allowing spaces): 6077, 6077 TT A1, 6077TTA1
        if (preg_match_all('/(\d{4})\s?T\s?T\s?A\s?1?/i', $porteur, $matches)) {
            foreach ($matches[0] as $rawCandidate) {
                $normalized = $this->normalizeMatricule($rawCandidate);
                // Ensure it ends with TTA1 (some matches might be incomplete)
                if (! str_ends_with($normalized, 'TTA1')) {
                    $normalized = preg_replace('/TTA?$/', 'TTA1', $normalized);
                }
                if ($matriculeMap->has($normalized)) {
                    return $matriculeMap->get($normalized);
                }
            }
        }
        return null;
    }

    private function matchDriver(string $porteur, $drivers): ?Driver
    {
        $porteurUpper = strtoupper($this->stripAccents($porteur));
        foreach ($drivers as $d) {
            $nameUpper = strtoupper($this->stripAccents($d->name));
            // Fuzzy match: first 2 name tokens of length >= 3
            $tokens = array_filter(preg_split('/\s+/', $nameUpper), fn ($t) => strlen($t) >= 3);
            $matchedTokens = 0;
            foreach ($tokens as $tok) {
                if (str_contains($porteurUpper, $tok)) {
                    $matchedTokens++;
                }
            }
            if ($matchedTokens >= 2) {
                return $d;
            }
        }
        return null;
    }
}
