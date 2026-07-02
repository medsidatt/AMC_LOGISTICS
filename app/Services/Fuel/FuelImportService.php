<?php

namespace App\Services\Fuel;

use App\Domain\Fuel\Classification\FuelImportClassifier;
use App\Domain\Fuel\Classification\FuelImportReference;
use App\Domain\Fuel\Classification\FuelImportReferenceReader;
use App\Domain\Fuel\ClassificationPolicy;
use App\Domain\Fuel\Parsing\ParsedFuelImportRow;
use App\Domain\Fuel\ValueObjects\FuelTransactionClassification;
use App\Domain\Fuel\ValueObjects\PolicyOutcome;
use App\Enums\Fuel\KpiEligibility;
use App\Enums\Fuel\PersistenceDecision;
use App\Enums\Fuel\ReviewDecision;
use App\Models\FuelCardTransaction;
use App\Models\FuelImportBatch;
use App\Models\FuelImportRejection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * R8 Import Orchestrator — runs the complete pipeline and coordinates persistence. It OWNS NO business
 * rules: the classifier derives facts, ClassificationPolicy makes every decision, and this service
 * persists EXACTLY what the policy returns (accepted → FuelCardTransaction, rejected → FuelImportRejection,
 * proposal snapshot + effective = proposal at import; review_status = PENDING iff the policy required
 * review — no review outcome is invented). The whole run is one transaction (no partial imports).
 *
 * FK ids (truck/driver) are obtained from the already-loaded read-only reference purely for storage —
 * the service derives no findings and makes no decisions. Review events are NOT created at import
 * (they record reviewer decisions — the review workflow, R10).
 */
class FuelImportService
{
    private const REVIEW_PENDING = 'PENDING';
    private const REVIEW_NONE = 'NONE';

    public function __construct(
        private readonly EdkImportParser $parser,
        private readonly FuelImportReferenceReader $referenceReader,
        private readonly FuelImportClassifier $classifier,
        private readonly ClassificationPolicy $policy,
    ) {}

    public function import(string $contents, float $pricePerLitre, ?string $filename = null, ?int $userId = null): FuelImportBatch
    {
        $file = $this->parser->parse($contents);                          // 1. syntactic facts
        $reference = $this->referenceReader->read();                       // 2. reference (query-only)
        $classifications = $this->classifier->classifyFile($file, $reference); // 3. business facts

        return DB::transaction(function () use ($file, $reference, $classifications, $pricePerLitre, $filename, $userId) {
            $batch = FuelImportBatch::create([
                'source' => $file->source->value,
                'original_filename' => $filename,
                'total_rows' => $file->rowCount(),
                'imported_by' => $userId,
                'policy_version' => $this->policy->version(),
            ]);

            $stats = $this->freshStats();
            foreach ($file->rows as $i => $row) {
                $classification = $classifications[$i];
                $outcome = $this->policy->decide($classification);        // 4. decision (policy owns it)
                $this->persistRow($row, $classification, $outcome, $reference, $batch->id, $pricePerLitre, $userId, $stats); // 5. persist
                $this->tally($classification, $outcome, $stats);
            }

            $batch->update($this->batchCounters($stats));

            return $batch->fresh();
        });
    }

    /**
     * R9 — run the SAME pipeline (Parser → Reference → Classifier → ClassificationPolicy) WITHOUT
     * persisting, for the preview endpoint. Returns the parsed facts + findings + policy decisions +
     * summary so the controller has no pipeline logic of its own. Additive to R8 import() (unchanged).
     *
     * @return array<string,mixed>
     */
    public function preview(string $contents, float $pricePerLitre): array
    {
        $file = $this->parser->parse($contents);
        $reference = $this->referenceReader->read();
        $classifications = $this->classifier->classifyFile($file, $reference);

        $stats = $this->freshStats();
        $rows = [];
        foreach ($file->rows as $i => $row) {
            $classification = $classifications[$i];
            $outcome = $this->policy->decide($classification);
            $this->tally($classification, $outcome, $stats);
            $outcome->isAccepted() ? $stats['accepted']++ : $stats['rejected']++;

            $litres = ($row->amount !== null && $row->amount > 0 && $pricePerLitre > 0) ? round($row->amount / $pricePerLitre, 2) : null;
            $rows[] = [
                'line' => $row->lineNumber,
                'transaction_ref' => $row->transactionRef,
                'date' => $row->occurredAt !== null ? Carbon::parse($row->occurredAt)->format('d/m/Y H:i') : $row->occurredAtRaw,
                'amount' => $row->amount,
                'estimated_litres' => $litres,
                'card' => $row->cardNumber,
                'plate' => $row->normalizedRegistration,
                'holder' => $row->holderRaw,
                'type' => $classification->type->value,
                'source' => $classification->source->value,
                'persistence' => $outcome->persistence->value,
                'kpi_eligible' => $outcome->isKpiEligible(),
                'review' => $outcome->review->value,
                'technical_findings' => $classification->findings->technicalCodes(),
                'business_findings' => $classification->findings->businessCodes(),
            ];
        }

        return [
            'source' => $file->source->value,
            'total_rows' => $file->rowCount(),
            'summary' => $this->batchCounters($stats),
            'rows' => $rows,
            'file_errors' => array_map(fn ($e) => $e->toArray(), $file->fileErrors),
        ];
    }

    private function persistRow(
        ParsedFuelImportRow $row,
        FuelTransactionClassification $classification,
        PolicyOutcome $outcome,
        FuelImportReference $reference,
        int $batchId,
        float $price,
        ?int $userId,
        array &$stats,
    ): void {
        $truck = $reference->truckFor($row->normalizedRegistration); // FK lookup for storage only
        $truckId = $truck['id'] ?? null;
        $driverId = $reference->driverIdForHolder($row->holderRaw);
        $litres = ($row->amount !== null && $row->amount > 0 && $price > 0) ? round($row->amount / $price, 2) : null;

        if ($outcome->isAccepted()) {
            FuelCardTransaction::create([
                'source' => $classification->source->value,
                'transaction_type' => $classification->type->value,
                'truck_id' => $truckId,
                'driver_id' => $driverId,
                'transaction_ref' => $row->transactionRef,
                'card_number' => $row->cardNumber,
                'holder_raw' => $row->holderRaw,
                'detected_plate' => $row->normalizedRegistration,
                'amount_fcfa' => $row->amount,
                'estimated_litres' => $litres,
                'price_per_litre' => $price,
                'occurred_at' => $row->occurredAt,
                'imported_by' => $userId,
                'fuel_import_batch_id' => $batchId,
                // Immutable validator proposal snapshot.
                'proposed_technical_findings' => $classification->findings->technicalCodes(),
                'proposed_business_findings' => $classification->findings->businessCodes(),
                'proposed_kpi_eligible' => $outcome->isKpiEligible(),
                'policy_version' => $this->policy->version(),
                // Effective = proposal at import (persist exactly what the policy returned).
                'kpi_eligible' => $outcome->isKpiEligible(),
                'review_status' => $outcome->needsReview() ? self::REVIEW_PENDING : self::REVIEW_NONE,
            ]);
            $stats['accepted']++;

            return;
        }

        FuelImportRejection::create([
            'fuel_import_batch_id' => $batchId,
            'source' => $classification->source->value,
            'transaction_type' => $classification->type->value,
            'technical_findings' => $classification->findings->technicalCodes(),
            'reason_summary' => $this->reasonSummary($classification),
            'line_number' => $row->lineNumber,
            'raw_line' => $row->rawLine,
            'transaction_ref' => $row->transactionRef,
            'card_number' => $row->cardNumber,
            'holder_raw' => $row->holderRaw,
            'detected_plate' => $row->normalizedRegistration,
            'amount_fcfa' => $row->amount,
            'estimated_litres' => $litres,
            'occurred_at' => $row->occurredAt,
            'detected_truck_id' => $truckId,
            'detected_driver_id' => $driverId,
            'needs_review' => $outcome->needsReview(),
        ]);
        $stats['rejected']++;
    }

    private function reasonSummary(FuelTransactionClassification $classification): string
    {
        $labels = array_map(fn ($f) => $f->label(), $classification->findings->technical);

        return $labels === [] ? 'Rejet technique' : implode(' ; ', $labels);
    }

    private function tally(FuelTransactionClassification $classification, PolicyOutcome $outcome, array &$stats): void
    {
        $stats['source'][$classification->source->value] = ($stats['source'][$classification->source->value] ?? 0) + 1;
        $stats['type'][$classification->type->value] = ($stats['type'][$classification->type->value] ?? 0) + 1;
        foreach ($classification->findings->technicalCodes() as $code) {
            $stats['tech'][$code] = ($stats['tech'][$code] ?? 0) + 1;
        }
        foreach ($classification->findings->businessCodes() as $code) {
            $stats['biz'][$code] = ($stats['biz'][$code] ?? 0) + 1;
        }
        $stats['persist'][$outcome->persistence->value]++;
        $stats['kpi'][$outcome->kpiEligibility->value]++;
        $stats['review'][$outcome->review->value]++;
    }

    private function freshStats(): array
    {
        return [
            'accepted' => 0,
            'rejected' => 0,
            'source' => [],
            'type' => [],
            'tech' => [],
            'biz' => [],
            'persist' => [PersistenceDecision::ACCEPT->value => 0, PersistenceDecision::REJECT->value => 0],
            'kpi' => [KpiEligibility::ELIGIBLE->value => 0, KpiEligibility::NOT_ELIGIBLE->value => 0],
            'review' => [ReviewDecision::REQUIRED->value => 0, ReviewDecision::NONE->value => 0],
        ];
    }

    private function batchCounters(array $stats): array
    {
        return [
            'accepted_rows' => $stats['accepted'],
            'rejected_rows' => $stats['rejected'],
            'source_counts' => $stats['source'],
            'type_counts' => $stats['type'],
            'technical_finding_counts' => $stats['tech'],
            'business_finding_counts' => $stats['biz'],
            'decision_counts' => [
                'persistence' => $stats['persist'],
                'kpi' => $stats['kpi'],
                'review' => $stats['review'],
            ],
            'policy_version' => $this->policy->version(),
        ];
    }
}
