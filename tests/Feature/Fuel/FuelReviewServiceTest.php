<?php

namespace Tests\Feature\Fuel;

use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\ReviewOutcome;
use App\Enums\Fuel\TransactionType;
use App\Models\Auth\User;
use App\Models\FuelCardTransaction;
use App\Models\FuelTransactionReviewEvent;
use App\Models\Truck;
use App\Services\Fuel\FuelReviewService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * R10 — the review service corrects facts on a PENDING transaction, delegates the KPI decision to
 * ClassificationPolicy (never bypasses it), appends an immutable event, and never mutates the proposal.
 */
class FuelReviewServiceTest extends TestCase
{
    use DatabaseTransactions;

    private FuelReviewService $service;
    private int $reviewerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FuelReviewService::class);
        $this->reviewerId = (int) User::query()->firstOrFail()->id;
    }

    private function pendingTx(array $over = []): FuelCardTransaction
    {
        return FuelCardTransaction::create(array_merge([
            'source' => FuelSource::EDK_CARD->value,
            'transaction_type' => TransactionType::FUEL_RECHARGE->value,
            'truck_id' => null,
            'transaction_ref' => 'REV-'.uniqid(),
            'amount_fcfa' => 210000,
            'estimated_litres' => 287,
            'price_per_litre' => 730,
            'occurred_at' => '2030-06-01 10:00:00',
            'detected_plate' => '9999TTA1',
            'kpi_eligible' => false,
            'review_status' => 'PENDING',
            'proposed_technical_findings' => [],
            'proposed_business_findings' => ['UNKNOWN_TRUCK'],
            'proposed_kpi_eligible' => false,
            'policy_version' => 'v1',
        ], $over));
    }

    public function test_re_attributed_sets_truck_and_policy_makes_it_eligible(): void
    {
        $truck = Truck::where('is_active', true)->firstOrFail();
        $tx = $this->pendingTx();

        $event = $this->service->resolve($tx, ReviewOutcome::RE_ATTRIBUTED, $this->reviewerId, 'Camion identifié', (int) $truck->id);
        $tx->refresh();

        $this->assertSame((int) $truck->id, (int) $tx->truck_id);
        $this->assertTrue($tx->kpi_eligible, 'policy re-decides: FUEL_RECHARGE with truck resolved → eligible');
        $this->assertSame('RESOLVED', $tx->review_status);
        $this->assertSame(ReviewOutcome::RE_ATTRIBUTED->value, $tx->review_outcome);
        $this->assertNotNull($tx->reviewed_at);
        $this->assertSame($this->reviewerId, (int) $tx->reviewed_by);

        // event before/after
        $this->assertSame(false, $event->before['kpi_eligible']);
        $this->assertSame('PENDING', $event->before['review_status']);
        $this->assertSame(true, $event->after['kpi_eligible']);
        $this->assertSame('RESOLVED', $event->after['review_status']);
        $this->assertSame($this->reviewerId, (int) $event->reviewer_id);
        $this->assertSame('Camion identifié', $event->note);
    }

    public function test_promotion_without_reattribution_does_not_force_eligibility(): void
    {
        // Fact-driven: PROMOTED_TO_KPI without correcting the truck leaves UNKNOWN_TRUCK standing, so the
        // policy keeps it NOT eligible. The reviewer cannot bypass the policy via the outcome label.
        $tx = $this->pendingTx();
        $this->service->resolve($tx, ReviewOutcome::PROMOTED_TO_KPI, $this->reviewerId);
        $this->assertFalse($tx->refresh()->kpi_eligible);
        $this->assertSame('RESOLVED', $tx->review_status);
    }

    public function test_review_outcome_is_audit_metadata_only_facts_drive_eligibility(): void
    {
        $truck = Truck::where('is_active', true)->firstOrFail();

        // Different outcomes, SAME uncorrected facts → SAME eligibility (not eligible): the outcome is a label.
        $a = $this->pendingTx();
        $this->service->resolve($a, ReviewOutcome::CONFIRMED_NON_OPERATIONAL, $this->reviewerId);
        $b = $this->pendingTx();
        $this->service->resolve($b, ReviewOutcome::DISMISSED, $this->reviewerId);
        $this->assertFalse($a->refresh()->kpi_eligible);
        $this->assertFalse($b->refresh()->kpi_eligible);

        // Eligibility flips only when the FACT is corrected (re-attributed to an active fleet truck).
        $c = $this->pendingTx();
        $this->service->resolve($c, ReviewOutcome::RE_ATTRIBUTED, $this->reviewerId, null, (int) $truck->id);
        $this->assertTrue($c->refresh()->kpi_eligible);
    }

    public function test_promote_never_bypasses_policy_unknown_type_stays_not_eligible(): void
    {
        // Reviewer cannot force a non-FUEL_RECHARGE to be KPI-eligible — the policy type gate applies.
        $tx = $this->pendingTx([
            'source' => FuelSource::EDK_ACCOUNT->value,
            'transaction_type' => TransactionType::UNKNOWN->value,
            'proposed_business_findings' => [],
        ]);
        $this->service->resolve($tx, ReviewOutcome::PROMOTED_TO_KPI, $this->reviewerId);
        $this->assertFalse($tx->refresh()->kpi_eligible, 'policy keeps UNKNOWN type NOT_ELIGIBLE despite promotion');
        $this->assertSame('RESOLVED', $tx->review_status);
    }

    public function test_confirmed_non_operational_stays_not_eligible(): void
    {
        $tx = $this->pendingTx();
        $this->service->resolve($tx, ReviewOutcome::CONFIRMED_NON_OPERATIONAL, $this->reviewerId);
        $this->assertFalse($tx->refresh()->kpi_eligible);
        $this->assertSame('RESOLVED', $tx->review_status);
    }

    public function test_proposal_snapshot_is_immutable_after_review(): void
    {
        $tx = $this->pendingTx();
        $this->service->resolve($tx, ReviewOutcome::PROMOTED_TO_KPI, $this->reviewerId);
        $tx->refresh();

        $this->assertSame(['UNKNOWN_TRUCK'], $tx->proposed_business_findings, 'proposal findings never change');
        $this->assertSame([], $tx->proposed_technical_findings);
        $this->assertFalse($tx->proposed_kpi_eligible, 'proposed_kpi_eligible frozen');
        $this->assertSame('v1', $tx->policy_version);
    }

    public function test_each_action_appends_exactly_one_immutable_event(): void
    {
        $tx = $this->pendingTx();
        $this->service->resolve($tx, ReviewOutcome::DISMISSED, $this->reviewerId, 'note');
        $this->assertSame(1, FuelTransactionReviewEvent::where('fuel_card_transaction_id', $tx->id)->count());
    }

    public function test_reattribution_redelegates_to_classifier_and_catches_card_mismatch(): void
    {
        // The review service must NOT hand-clear truck findings: it re-derives findings via the classifier
        // against the corrected truck. Re-attributing a card's transaction to a truck that does NOT own that
        // card raises CARD_MISMATCH → the policy keeps it NOT eligible. (A naive "clear UNKNOWN_TRUCK" would
        // have wrongly made it eligible.)
        $trucks = Truck::where('is_active', true)->take(2)->get();
        if ($trucks->count() < 2) {
            $this->markTestSkipped('Need two active trucks to assert a re-attribution card mismatch.');
        }
        [$cardOwnerTruck, $otherTruck] = [$trucks[0], $trucks[1]];
        $card = 'REV-CARD-'.uniqid();

        // Establish that $card belongs to $cardOwnerTruck (card→truck history the classifier reads).
        FuelCardTransaction::create([
            'source' => FuelSource::EDK_CARD->value,
            'transaction_type' => TransactionType::FUEL_RECHARGE->value,
            'truck_id' => $cardOwnerTruck->id,
            'transaction_ref' => 'REV-OWN-'.uniqid(),
            'card_number' => $card,
            'amount_fcfa' => 150000,
            'price_per_litre' => 730,
            'occurred_at' => '2030-05-01 08:00:00',
            'kpi_eligible' => true,
            'review_status' => 'NONE',
        ]);

        // Pending row on the SAME card, re-attributed to a DIFFERENT active truck → card mismatch.
        $tx = $this->pendingTx(['card_number' => $card]);
        $this->service->resolve($tx, ReviewOutcome::RE_ATTRIBUTED, $this->reviewerId, null, (int) $otherTruck->id);
        $tx->refresh();

        $this->assertSame((int) $otherTruck->id, (int) $tx->truck_id);
        $this->assertFalse($tx->kpi_eligible, 'classifier re-derives CARD_MISMATCH for the corrected truck → policy keeps it not eligible');
    }

    public function test_review_never_mutates_the_transaction_date(): void
    {
        // occurred_at is immutable financial truth: resolving a row must NOT rewrite the transaction date
        // (regression for the inherited TIMESTAMP ON UPDATE CURRENT_TIMESTAMP defect).
        $truck = Truck::where('is_active', true)->firstOrFail();
        $tx = $this->pendingTx(['occurred_at' => '2026-06-01 11:47:26']);

        $this->service->resolve($tx, ReviewOutcome::RE_ATTRIBUTED, $this->reviewerId, null, (int) $truck->id);

        $this->assertSame('2026-06-01 11:47:26', $tx->refresh()->occurred_at->toDateTimeString());
    }

    public function test_cannot_review_a_transaction_that_is_not_pending(): void
    {
        $tx = $this->pendingTx();
        $this->service->resolve($tx, ReviewOutcome::DISMISSED, $this->reviewerId);

        $this->expectException(DomainException::class);
        $this->service->resolve($tx->refresh(), ReviewOutcome::DISMISSED, $this->reviewerId);
    }

    public function test_re_attribution_requires_an_active_truck(): void
    {
        $tx = $this->pendingTx();
        $this->expectException(InvalidArgumentException::class);
        $this->service->resolve($tx, ReviewOutcome::RE_ATTRIBUTED, $this->reviewerId, null, 99999999);
    }
}
