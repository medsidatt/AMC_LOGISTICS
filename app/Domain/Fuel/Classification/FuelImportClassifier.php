<?php

namespace App\Domain\Fuel\Classification;

use App\Domain\Fuel\Parsing\ParsedFuelImportFile;
use App\Domain\Fuel\Parsing\ParsedFuelImportRow;
use App\Domain\Fuel\Parsing\ParseError;
use App\Domain\Fuel\ValueObjects\FuelTransactionClassification;
use App\Domain\Fuel\ValueObjects\ValidationFindings;
use App\Enums\Fuel\BusinessFinding;
use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TechnicalFinding;
use App\Enums\Fuel\TransactionType;

/**
 * R7 Classifier — consumes immutable parsed rows + a read-only {@see FuelImportReference} and produces
 * immutable BUSINESS FACTS ({@see FuelTransactionClassification} = TransactionType + FuelSource +
 * ValidationFindings). It is the ONLY input to ClassificationPolicy — but it NEVER calls the policy and
 * NEVER decides persistence / KPI eligibility / review. It is pure: no DB, no persistence, no imports
 * (all reference data is pre-loaded into the reference snapshot).
 *
 * Reuses the resolution rules of the legacy validator; invents no new rules. Business (truck/card/driver)
 * findings apply only to FUEL_RECHARGE — account movements are not truck-attributed.
 */
class FuelImportClassifier
{
    /**
     * Classify every row of a file, threading batch state (within-file duplicate refs + card owners).
     *
     * @return list<FuelTransactionClassification>
     */
    public function classifyFile(ParsedFuelImportFile $file, FuelImportReference $reference): array
    {
        $seenRefs = [];
        $batchCardOwner = [];
        $out = [];

        foreach ($file->rows as $row) {
            $out[] = $this->classify($row, $reference, $seenRefs, $batchCardOwner);

            // Register AFTER classifying so a row never flags itself.
            if ($row->transactionRef !== null) {
                $seenRefs[$row->transactionRef] = true;
            }
            if ($row->source === FuelSource::EDK_CARD && $row->cardNumber !== null) {
                $truck = $reference->truckFor($row->normalizedRegistration);
                if ($truck !== null && ! isset($batchCardOwner[$row->cardNumber]) && $reference->cardOwnerTruckId($row->cardNumber) === null) {
                    $batchCardOwner[$row->cardNumber] = $truck['id'];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string,bool>  $seenRefs  refs already seen earlier in the batch
     * @param  array<string,int>  $batchCardOwner  card → truck established earlier in the batch
     */
    public function classify(ParsedFuelImportRow $row, FuelImportReference $reference, array $seenRefs = [], array $batchCardOwner = []): FuelTransactionClassification
    {
        $type = $this->transactionType($row);

        $codes = array_map(fn (ParseError $e) => $e->code, $row->errors);

        // A structurally malformed row yields exactly one finding — nothing else can be trusted.
        if (in_array(ParseError::MALFORMED_ROW, $codes, true)) {
            return new FuelTransactionClassification($type, $row->source, new ValidationFindings([TechnicalFinding::MALFORMED_ROW]));
        }

        return new FuelTransactionClassification(
            $type,
            $row->source,
            new ValidationFindings(
                $this->technicalFindings($row, $codes, $reference, $seenRefs),
                $type === TransactionType::FUEL_RECHARGE ? $this->businessFindings($row, $reference, $batchCardOwner) : [],
            ),
        );
    }

    /**
     * Re-derive BUSINESS findings alone for a row whose TRUCK fact was corrected during manual review.
     * Technical findings are immutable row facts (structure/date/amount/duplicate) and are NOT recomputed
     * here — only truck-dependent business findings can change. Applies the SAME rules as import (via the
     * private {@see businessFindings()}), so the review path never re-implements classification.
     *
     * @return list<BusinessFinding>
     */
    public function businessFindingsFor(ParsedFuelImportRow $row, FuelImportReference $reference): array
    {
        return $this->transactionType($row) === TransactionType::FUEL_RECHARGE
            ? $this->businessFindings($row, $reference, [])
            : [];
    }

    private function transactionType(ParsedFuelImportRow $row): TransactionType
    {
        return match ($row->source) {
            FuelSource::EDK_CARD => TransactionType::FUEL_RECHARGE,
            FuelSource::EDK_ACCOUNT => $this->accountType($row->mode),
            default => TransactionType::UNKNOWN,
        };
    }

    private function accountType(?string $mode): TransactionType
    {
        $m = mb_strtolower($mode ?? '');
        if (str_contains($m, 'transfert')) {
            return TransactionType::ACCOUNT_TRANSFER;
        }
        if (str_contains($m, 'recharg') || str_contains($m, 'espece') || str_contains($m, 'espèce')) {
            return TransactionType::ACCOUNT_RECHARGE;
        }

        return TransactionType::UNKNOWN;
    }

    /**
     * @param  list<string>  $codes
     * @param  array<string,bool>  $seenRefs
     * @return list<TechnicalFinding>
     */
    private function technicalFindings(ParsedFuelImportRow $row, array $codes, FuelImportReference $reference, array $seenRefs): array
    {
        $findings = [];

        if (in_array(ParseError::MISSING_TRANSACTION_REF, $codes, true)) {
            $findings[] = TechnicalFinding::MALFORMED_ROW; // no reference → the row is unusable
        }
        if ($row->occurredAt === null) {
            $findings[] = TechnicalFinding::INVALID_DATE;
        }
        if ($row->amount === null || $row->amount <= 0) {
            $findings[] = TechnicalFinding::INVALID_AMOUNT;
        }
        if ($row->transactionRef !== null
            && ($reference->refAlreadyExists($row->transactionRef) || isset($seenRefs[$row->transactionRef]))) {
            $findings[] = TechnicalFinding::DUPLICATE_TRANSACTION;
        }

        return $findings;
    }

    /** @return list<BusinessFinding> */
    private function businessFindings(ParsedFuelImportRow $row, FuelImportReference $reference, array $batchCardOwner): array
    {
        $findings = [];
        $truck = $reference->truckFor($row->normalizedRegistration);

        if ($truck === null) {
            $findings[] = BusinessFinding::UNKNOWN_TRUCK;

            return $findings; // no truck → card/driver mismatch cannot be assessed
        }

        if (! $truck['active']) {
            $findings[] = BusinessFinding::INACTIVE_TRUCK;
        }

        if ($row->cardNumber !== null) {
            $owner = $reference->cardOwnerTruckId($row->cardNumber) ?? ($batchCardOwner[$row->cardNumber] ?? null);
            if ($owner !== null && (int) $owner !== (int) $truck['id']) {
                $findings[] = BusinessFinding::CARD_MISMATCH;
            }
        }

        $driverId = $reference->driverIdForHolder($row->holderRaw);
        $assigned = $reference->activeDriverIds($truck['id']);
        if ($driverId !== null && $assigned !== null && ! in_array($driverId, $assigned, true)) {
            $findings[] = BusinessFinding::DRIVER_MISMATCH;
        }

        return $findings;
    }
}
