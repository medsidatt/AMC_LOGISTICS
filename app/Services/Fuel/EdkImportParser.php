<?php

namespace App\Services\Fuel;

use App\Domain\Fuel\Parsing\ParsedFuelImportFile;
use App\Domain\Fuel\Parsing\ParsedFuelImportRow;
use App\Domain\Fuel\Parsing\ParseError;
use App\Enums\Fuel\FuelSource;
use Carbon\Carbon;

/**
 * R6 Import Parser — converts a raw EDK CSV export into immutable NORMALIZED FACTS
 * ({@see ParsedFuelImportFile}). Facts only: it detects the export format (card vs account),
 * normalizes values (dates, amounts, card numbers, registration strings, references), preserves the
 * originals for audit, and records syntactic ParseErrors. It performs ZERO business decisions, ZERO
 * database access, ZERO truck/driver resolution, and ZERO persistence — that is R7+ (classifier /
 * ClassificationPolicy). Its output is ready to feed the classifier in R7.
 */
class EdkImportParser
{
    private const FRENCH_MONTHS = [
        'jan' => '01', 'fev' => '02', 'mar' => '03', 'avr' => '04',
        'mai' => '05', 'juin' => '06', 'juil' => '07', 'aou' => '08',
        'sep' => '09', 'oct' => '10', 'nov' => '11', 'dec' => '12',
        'jui' => '06', // EDK truncates June to 3-char "Jui" (July stays 4-char "Juil")
    ];

    public function parse(string $contents): ParsedFuelImportFile
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        $source = $this->detectSource($lines);
        if ($source === null) {
            return new ParsedFuelImportFile(
                FuelSource::CSV,
                [],
                [new ParseError(ParseError::UNKNOWN_FORMAT, 'Format EDK non reconnu (ni « Numero carte » ni « Mode de recharge »).')],
            );
        }

        $rows = [];
        foreach ($lines as $idx => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            // Skip header + footer (structural, not data).
            if (stripos($trimmed, 'ID Transaction') !== false || stripos($trimmed, 'Montant Total') !== false) {
                continue;
            }

            $rows[] = $this->parseRow($idx + 1, $line, $trimmed, $source);
        }

        return new ParsedFuelImportFile($source, $rows);
    }

    /** @param array<int,string> $lines */
    private function detectSource(array $lines): ?FuelSource
    {
        foreach ($lines as $line) {
            if (stripos($line, 'Numero carte') !== false) {
                return FuelSource::EDK_CARD;
            }
            if (stripos($line, 'Mode de recharge') !== false) {
                return FuelSource::EDK_ACCOUNT;
            }
        }

        return null;
    }

    private function parseRow(int $lineNumber, string $rawLine, string $trimmed, FuelSource $source): ParsedFuelImportRow
    {
        $cols = array_map('trim', explode(';', $trimmed));

        if (count($cols) < 6) {
            return new ParsedFuelImportRow(
                lineNumber: $lineNumber, rawLine: $rawLine, source: $source,
                transactionRef: null, occurredAt: null, occurredAtRaw: null,
                amount: null, amountRaw: null, cardNumber: null, normalizedRegistration: null,
                holderRaw: null, mode: null, note: null,
                errors: [new ParseError(ParseError::MALFORMED_ROW, 'Ligne EDK malformée (moins de 6 colonnes).', null, $lineNumber)],
            );
        }

        // Both families: [0] ID Transaction (always 0, ignored), [1] N transaction, [2] Date, [3] Montant.
        // Card:    [4] Numero carte, [5] Porteur.   Account: [4] Mode de recharge, [5] Commentaires.
        [, $refRaw, $dateRaw, $amountRaw, $col4, $col5] = $cols;
        $errors = [];

        $transactionRef = $this->normalizeReference($refRaw);
        if ($transactionRef === null) {
            $errors[] = new ParseError(ParseError::MISSING_TRANSACTION_REF, 'Référence de transaction (N transaction) absente.', 'transaction_ref', $lineNumber);
        }

        $occurredAt = $this->normalizeDate($dateRaw);
        if ($occurredAt === null && trim($dateRaw) !== '') {
            $errors[] = new ParseError(ParseError::UNPARSEABLE_DATE, 'Date illisible : '.$dateRaw, 'occurred_at', $lineNumber);
        } elseif (trim($dateRaw) === '') {
            $errors[] = new ParseError(ParseError::UNPARSEABLE_DATE, 'Date absente.', 'occurred_at', $lineNumber);
        }

        $amount = $this->normalizeAmount($amountRaw);
        if ($amount === null) {
            $errors[] = new ParseError(ParseError::UNPARSEABLE_AMOUNT, 'Montant illisible : '.$amountRaw, 'amount', $lineNumber);
        }

        $isCard = $source === FuelSource::EDK_CARD;

        return new ParsedFuelImportRow(
            lineNumber: $lineNumber,
            rawLine: $rawLine,
            source: $source,
            transactionRef: $transactionRef,
            occurredAt: $occurredAt,
            occurredAtRaw: $dateRaw !== '' ? $dateRaw : null,
            amount: $amount,
            amountRaw: $amountRaw !== '' ? $amountRaw : null,
            cardNumber: $isCard ? $this->normalizeCardNumber($col4) : null,
            normalizedRegistration: $isCard ? $this->normalizeRegistration($col5) : null,
            holderRaw: $isCard ? ($col5 !== '' ? $col5 : null) : null,
            mode: $isCard ? null : ($col4 !== '' ? $col4 : null),
            note: $isCard ? null : ($col5 !== '' ? $col5 : null),
            errors: $errors,
        );
    }

    private function normalizeReference(string $raw): ?string
    {
        $ref = trim($raw);

        return $ref === '' ? null : $ref;
    }

    private function normalizeCardNumber(string $raw): ?string
    {
        $card = preg_replace('/\s+/', '', trim($raw));

        return ($card === null || $card === '') ? null : $card;
    }

    /** Extract + normalize a "NNNN…TTA1" registration string from free text — string only, no truck lookup. */
    private function normalizeRegistration(string $porteur): ?string
    {
        if (! preg_match('/(\d{4})\s?T\s?T\s?A\s?1?/i', $porteur, $m)) {
            return null;
        }
        $normalized = strtoupper(preg_replace('/\s+/', '', $m[0]));
        if (! str_ends_with($normalized, 'TTA1')) {
            $normalized = preg_replace('/TTA?$/', 'TTA1', $normalized);
        }

        return $normalized;
    }

    private function normalizeAmount(string $raw): ?float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', $raw);
        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }

    /** Normalize "13-Mai-2026  22:12:23" (French month, possibly truncated) → 'Y-m-d H:i:s'. */
    private function normalizeDate(string $raw): ?string
    {
        $raw = preg_replace('/\s+/', ' ', trim($raw));
        if ($raw === '' || ! preg_match('/^(\d{1,2})-([A-Za-zÀ-ÿ]{3,4})-(\d{4})\s+(\d{1,2}:\d{2}(:\d{2})?)$/u', $raw, $m)) {
            return null;
        }

        $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $monthKey = strtolower($this->stripAccents($m[2]));
        $month = self::FRENCH_MONTHS[substr($monthKey, 0, 4)] ?? self::FRENCH_MONTHS[substr($monthKey, 0, 3)] ?? null;
        if ($month === null) {
            return null;
        }

        $time = $m[4];
        if (substr_count($time, ':') === 1) {
            $time .= ':00';
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', "{$m[3]}-{$month}-{$day} {$time}")->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function stripAccents(string $s): string
    {
        return strtr($s, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'À' => 'A',
        ]);
    }
}
