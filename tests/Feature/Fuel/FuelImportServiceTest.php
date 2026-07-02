<?php

namespace Tests\Feature\Fuel;

use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TransactionType;
use App\Models\FuelCardTransaction;
use App\Models\FuelImportBatch;
use App\Models\FuelImportRejection;
use App\Models\FuelTransactionReviewEvent;
use App\Models\Truck;
use App\Services\Fuel\FuelImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * R8 — end-to-end orchestration: Parser → Reference → Classifier → ClassificationPolicy → Persistence.
 * Verifies the service persists exactly the policy output and never loses financial truth.
 */
class FuelImportServiceTest extends TestCase
{
    use DatabaseTransactions;

    private Truck $truck;
    private string $mat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->truck = Truck::where('is_active', true)->firstOrFail();
        $this->mat = (string) $this->truck->matricule;
    }

    private function service(): FuelImportService
    {
        return app(FuelImportService::class);
    }

    private function cardCsv(): string
    {
        return implode("\n", [
            ' ID Transaction; N transaction; Date; Montant; Numero carte;  Porteur',
            "0;R8-CLEAN-1;01-Mai-2030  10:00:00;210000;R8CARD1;{$this->mat} ZZZUNIQUEHOLDER",
            '0;R8-UNK-1;01-Mai-2030  11:00:00;210000;R8CARD2;9999TTA1 Personne Inconnue',
            '0;R8-BAD;too;few',
        ]);
    }

    public function test_pipeline_persists_accepted_and_rejected_with_counters(): void
    {
        $batch = $this->service()->import($this->cardCsv(), 730.0, 'r8.csv', null);

        $this->assertSame(FuelSource::EDK_CARD->value, $batch->source);
        $this->assertSame(3, $batch->total_rows);
        $this->assertSame(2, $batch->accepted_rows); // clean + unknown-truck (financial truth kept)
        $this->assertSame(1, $batch->rejected_rows); // malformed
        $this->assertSame($batch->total_rows, $batch->accepted_rows + $batch->rejected_rows, 'nothing lost');

        $this->assertSame(2, FuelCardTransaction::where('fuel_import_batch_id', $batch->id)->count());
        $this->assertSame(1, FuelImportRejection::where('fuel_import_batch_id', $batch->id)->count());

        // counters populated (structural, not decisions)
        $this->assertSame('v1', $batch->policy_version);
        $this->assertSame(['EDK_CARD' => 3], $batch->source_counts);
        $this->assertArrayHasKey('persistence', $batch->decision_counts);
        $this->assertSame(2, $batch->decision_counts['persistence']['ACCEPT']);
        $this->assertSame(1, $batch->decision_counts['persistence']['REJECT']);
    }

    public function test_clean_recharge_is_kpi_eligible_with_proposal_snapshot(): void
    {
        $batch = $this->service()->import($this->cardCsv(), 730.0);
        $tx = FuelCardTransaction::where('transaction_ref', 'R8-CLEAN-1')->firstOrFail();

        $this->assertSame((int) $this->truck->id, (int) $tx->truck_id);
        $this->assertSame(TransactionType::FUEL_RECHARGE, $tx->transaction_type);
        $this->assertTrue($tx->kpi_eligible);
        $this->assertSame('NONE', $tx->review_status);
        $this->assertSame(287.67, (float) $tx->estimated_litres); // 210000 / 730
        // immutable proposal snapshot = effective at import
        $this->assertTrue($tx->proposed_kpi_eligible);
        $this->assertSame([], $tx->proposed_technical_findings);
        $this->assertSame([], $tx->proposed_business_findings);
        $this->assertSame('v1', $tx->policy_version);
    }

    public function test_financial_truth_preserved_unknown_truck_is_accepted_not_lost(): void
    {
        $batch = $this->service()->import($this->cardCsv(), 730.0);
        $tx = FuelCardTransaction::where('transaction_ref', 'R8-UNK-1')->firstOrFail();

        $this->assertNull($tx->truck_id, 'a non-fleet transaction has no truck but is still recorded');
        $this->assertSame('9999TTA1', $tx->detected_plate);
        $this->assertSame(210000.0, (float) $tx->amount_fcfa);
        $this->assertFalse($tx->kpi_eligible);
        $this->assertSame('PENDING', $tx->review_status);
        $this->assertSame(['UNKNOWN_TRUCK'], $tx->proposed_business_findings);
    }

    public function test_malformed_row_is_rejected_with_findings(): void
    {
        $batch = $this->service()->import($this->cardCsv(), 730.0);
        $rej = FuelImportRejection::where('fuel_import_batch_id', $batch->id)->firstOrFail();

        $this->assertSame(['MALFORMED_ROW'], $rej->technical_findings);
        $this->assertTrue($rej->needs_review);
        $this->assertSame('0;R8-BAD;too;few', $rej->raw_line); // original preserved
    }

    public function test_account_transfer_is_accepted_not_eligible_no_review(): void
    {
        $csv = implode("\n", [
            ' ID Transaction; N transaction; Date; Montant; Mode de recharge;  Commentaires',
            '0;R8-ACC-1;01-Mai-2030  10:00:00;210000;Transfert carte vers Compte;Transfert carte vers Compte',
        ]);
        $batch = $this->service()->import($csv, 730.0);
        $tx = FuelCardTransaction::where('transaction_ref', 'R8-ACC-1')->firstOrFail();

        $this->assertSame(FuelSource::EDK_ACCOUNT->value, $batch->source);
        $this->assertSame(TransactionType::ACCOUNT_TRANSFER, $tx->transaction_type);
        $this->assertFalse($tx->kpi_eligible);
        $this->assertSame('NONE', $tx->review_status);
        $this->assertNull($tx->truck_id);
    }

    public function test_reimport_is_idempotent_duplicates_are_rejected(): void
    {
        $this->service()->import($this->cardCsv(), 730.0);
        $secondBatch = $this->service()->import($this->cardCsv(), 730.0);

        // Ledger unchanged — the two card refs exist once each.
        $this->assertSame(2, FuelCardTransaction::whereIn('transaction_ref', ['R8-CLEAN-1', 'R8-UNK-1'])->count());
        // Second run: both card rows now DUPLICATE (rejected) + malformed → 0 accepted.
        $this->assertSame(0, $secondBatch->accepted_rows);
        $this->assertSame(3, $secondBatch->rejected_rows);
    }

    public function test_no_review_events_are_created_at_import(): void
    {
        $batch = $this->service()->import($this->cardCsv(), 730.0);
        $txnIds = FuelCardTransaction::where('fuel_import_batch_id', $batch->id)->pluck('id');

        $this->assertSame(0, FuelTransactionReviewEvent::whereIn('fuel_card_transaction_id', $txnIds)->count());
    }
}
