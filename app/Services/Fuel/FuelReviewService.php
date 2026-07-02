<?php

namespace App\Services\Fuel;

use App\Domain\Fuel\Classification\FuelImportClassifier;
use App\Domain\Fuel\Classification\FuelImportReferenceReader;
use App\Domain\Fuel\ClassificationPolicy;
use App\Domain\Fuel\Parsing\ParsedFuelImportRow;
use App\Domain\Fuel\ValueObjects\FuelTransactionClassification;
use App\Domain\Fuel\ValueObjects\ValidationFindings;
use App\Enums\Fuel\BusinessFinding;
use App\Enums\Fuel\ReviewOutcome;
use App\Enums\Fuel\ReviewStatus;
use App\Enums\Fuel\TechnicalFinding;
use App\Models\FuelCardTransaction;
use App\Models\FuelTransactionReviewEvent;
use App\Models\Truck;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * R10 Manual Review Workflow. A reviewer corrects FACTS on a PENDING transaction; this service applies
 * the correction to the EFFECTIVE values only, then delegates the KPI-eligibility decision to
 * ClassificationPolicy (never sets it directly — "review never bypasses the policy"). It appends exactly
 * one immutable FuelTransactionReviewEvent per action and leaves the proposal snapshot untouched forever.
 *
 * It does NOT invent findings or business rules: when a corrected truck changes the facts, it delegates
 * finding re-derivation to {@see FuelImportClassifier} and the decision to {@see ClassificationPolicy}.
 */
class FuelReviewService
{
    public function __construct(
        private readonly ClassificationPolicy $policy,
        private readonly FuelImportClassifier $classifier,
        private readonly FuelImportReferenceReader $referenceReader,
    ) {}

    /** Query-only pending-review queue. */
    public function pending(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return FuelCardTransaction::query()
            ->where('review_status', ReviewStatus::PENDING->value)
            ->with(['truck:id,matricule', 'importedBy:id,name'])
            ->when($filters['truck_id'] ?? null, fn ($q, $v) => $q->where('truck_id', $v))
            ->latest('occurred_at')
            ->paginate($perPage);
    }

    public function resolve(
        FuelCardTransaction $transaction,
        ReviewOutcome $outcome,
        ?int $reviewerId,
        ?string $note = null,
        ?int $truckId = null,
    ): FuelTransactionReviewEvent {
        if ($transaction->review_status !== ReviewStatus::PENDING->value) {
            throw new DomainException("Cette transaction n'est pas en attente de revue.");
        }

        return DB::transaction(function () use ($transaction, $outcome, $reviewerId, $note, $truckId) {
            $before = $this->snapshot($transaction);

            // Corrected FACT: the effective truck. (ReviewOutcome is only an audit label — it never
            // drives the decision.)
            $effectiveTruckId = $transaction->truck_id;
            $reattributed = $outcome === ReviewOutcome::RE_ATTRIBUTED;
            if ($reattributed) {
                $truck = Truck::where('id', $truckId)->where('is_active', true)->first();
                if (! $truck) {
                    throw new InvalidArgumentException('Un camion actif est requis pour la ré-attribution.');
                }
                $effectiveTruckId = (int) $truck->id;
            }

            // The policy (sole decider) recomputes eligibility from the CORRECTED FACTS, not the outcome.
            // When the truck fact changed, findings are RE-DERIVED BY THE CLASSIFIER (never rebuilt here).
            $classification = $reattributed
                ? $this->reclassifyForTruck($transaction, $effectiveTruckId)
                : $this->frozenClassification($transaction);
            $eligibility = $this->policy->decide($classification)->isKpiEligible();

            $transaction->forceFill([
                'truck_id' => $effectiveTruckId,
                'kpi_eligible' => $eligibility,
                'review_status' => ReviewStatus::RESOLVED->value,
                'review_outcome' => $outcome->value,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewerId,
            ])->save();

            // Append-only audit event (the proposal snapshot columns are never modified).
            return FuelTransactionReviewEvent::create([
                'fuel_card_transaction_id' => $transaction->id,
                'reviewer_id' => $reviewerId,
                'outcome' => $outcome->value,
                'note' => $note,
                'before' => $before,
                'after' => $this->snapshot($transaction->fresh()),
            ]);
        });
    }

    /** @return array{truck_id:?int, kpi_eligible:bool, review_status:?string} */
    private function snapshot(FuelCardTransaction $t): array
    {
        return [
            'truck_id' => $t->truck_id !== null ? (int) $t->truck_id : null,
            'kpi_eligible' => (bool) $t->kpi_eligible,
            'review_status' => $t->review_status,
        ];
    }

    /**
     * The classification EXACTLY as the classifier + policy produced it at import (no fact corrected).
     * Rebuilt from the immutable proposal snapshot; the findings are not re-derived because nothing changed.
     */
    private function frozenClassification(FuelCardTransaction $t): FuelTransactionClassification
    {
        return new FuelTransactionClassification(
            $t->transaction_type,
            $t->source,
            new ValidationFindings($this->frozenTechnical($t), $this->frozenBusiness($t)),
        );
    }

    /**
     * The corrected classification after RE-ATTRIBUTION: technical findings stay frozen (immutable row
     * facts), but the truck-dependent BUSINESS findings are RE-DERIVED BY {@see FuelImportClassifier}
     * against the corrected truck — so mismatches (card/driver) on the new truck are caught, and no
     * classification rule is re-implemented in the review service.
     */
    private function reclassifyForTruck(FuelCardTransaction $t, int $truckId): FuelTransactionClassification
    {
        $reference = $this->referenceReader->read();

        $row = new ParsedFuelImportRow(
            lineNumber: 0,
            rawLine: '',
            source: $t->source,
            transactionRef: $t->transaction_ref,
            occurredAt: $t->occurred_at?->format('Y-m-d H:i:s'),
            occurredAtRaw: null,
            amount: $t->amount_fcfa !== null ? (float) $t->amount_fcfa : null,
            amountRaw: null,
            cardNumber: $t->card_number,
            normalizedRegistration: $reference->registrationForTruckId($truckId),
            holderRaw: $t->holder_raw,
            mode: null,
            note: null,
        );

        $business = $this->classifier->businessFindingsFor($row, $reference);

        return new FuelTransactionClassification($t->transaction_type, $t->source, new ValidationFindings($this->frozenTechnical($t), $business));
    }

    /** @return list<TechnicalFinding> */
    private function frozenTechnical(FuelCardTransaction $t): array
    {
        return array_map(fn (string $c) => TechnicalFinding::from($c), $t->proposed_technical_findings ?? []);
    }

    /** @return list<BusinessFinding> */
    private function frozenBusiness(FuelCardTransaction $t): array
    {
        return array_map(fn (string $c) => BusinessFinding::from($c), $t->proposed_business_findings ?? []);
    }
}
