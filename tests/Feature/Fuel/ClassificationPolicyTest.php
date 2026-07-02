<?php

namespace Tests\Feature\Fuel;

use App\Domain\Fuel\ClassificationPolicy;
use App\Domain\Fuel\ValueObjects\FuelTransactionClassification;
use App\Domain\Fuel\ValueObjects\ValidationFindings;
use App\Enums\Fuel\BusinessFinding;
use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\KpiEligibility;
use App\Enums\Fuel\PersistenceDecision;
use App\Enums\Fuel\ReviewDecision;
use App\Enums\Fuel\TechnicalFinding;
use App\Enums\Fuel\TransactionType;
use Tests\TestCase;

/**
 * R3 — ClassificationPolicy is the single rule-holder. This proves the entire decision surface:
 * every (TransactionType × technical-subset × business-subset) combination is checked against an
 * INDEPENDENT oracle (the rules restated with hard-coded finding sets, not the enum methods).
 */
class ClassificationPolicyTest extends TestCase
{
    private ClassificationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ClassificationPolicy;
    }

    public function test_exhaustive_truth_table_matches_independent_oracle(): void
    {
        $tech = TechnicalFinding::cases();            // 4
        $bus = BusinessFinding::cases();              // 4
        $reviewTech = ['INVALID_DATE', 'INVALID_AMOUNT', 'MALFORMED_ROW']; // non-duplicate technical
        $checked = 0;

        foreach (TransactionType::cases() as $type) {
            for ($tm = 0; $tm < (1 << count($tech)); $tm++) {
                $techSel = [];
                foreach ($tech as $i => $t) {
                    if ($tm & (1 << $i)) {
                        $techSel[] = $t;
                    }
                }
                for ($bm = 0; $bm < (1 << count($bus)); $bm++) {
                    $busSel = [];
                    foreach ($bus as $i => $b) {
                        if ($bm & (1 << $i)) {
                            $busSel[] = $b;
                        }
                    }

                    $outcome = $this->policy->decide(new FuelTransactionClassification(
                        $type, FuelSource::EDK_CARD, new ValidationFindings($techSel, $busSel)
                    ));

                    // Independent oracle (every technical finding is fatal in v1).
                    $persistExpected = $techSel !== [] ? PersistenceDecision::REJECT : PersistenceDecision::ACCEPT;
                    $kpiExpected = ($type === TransactionType::FUEL_RECHARGE && $techSel === [] && $busSel === [])
                        ? KpiEligibility::ELIGIBLE : KpiEligibility::NOT_ELIGIBLE;
                    $techForcesReview = false;
                    foreach ($techSel as $t) {
                        if (in_array($t->value, $reviewTech, true)) {
                            $techForcesReview = true;
                            break;
                        }
                    }
                    $reviewExpected = ($busSel !== [] || $techForcesReview || $type === TransactionType::UNKNOWN)
                        ? ReviewDecision::REQUIRED : ReviewDecision::NONE;

                    $ctx = $type->value.' T['.implode(',', array_map(fn ($x) => $x->value, $techSel)).'] B['.implode(',', array_map(fn ($x) => $x->value, $busSel)).']';
                    $this->assertSame($persistExpected, $outcome->persistence, "persistence: $ctx");
                    $this->assertSame($kpiExpected, $outcome->kpiEligibility, "kpi: $ctx");
                    $this->assertSame($reviewExpected, $outcome->review, "review: $ctx");
                    $checked++;
                }
            }
        }

        $this->assertSame(5 * 16 * 16, $checked, 'every type × technical-subset × business-subset covered');
    }

    public function test_row1_normal_fuel_recharge(): void
    {
        $o = $this->policy->decide(FuelTransactionClassification::make(TransactionType::FUEL_RECHARGE, FuelSource::EDK_CARD));
        $this->assertSame(PersistenceDecision::ACCEPT, $o->persistence);
        $this->assertSame(KpiEligibility::ELIGIBLE, $o->kpiEligibility);
        $this->assertSame(ReviewDecision::NONE, $o->review);
    }

    public function test_row2_ex_fleet_truck_AA463AQ(): void
    {
        $o = $this->policy->decide(new FuelTransactionClassification(
            TransactionType::FUEL_RECHARGE, FuelSource::EDK_CARD,
            new ValidationFindings([], [BusinessFinding::UNKNOWN_TRUCK])
        ));
        $this->assertTrue($o->isAccepted(), 'financial truth preserved');
        $this->assertFalse($o->isKpiEligible(), 'excluded from KPIs');
        $this->assertTrue($o->needsReview(), 'queued for investigation');
    }

    public function test_rows7_8_account_movements_accept_not_eligible_no_review(): void
    {
        foreach ([TransactionType::ACCOUNT_RECHARGE, TransactionType::ACCOUNT_TRANSFER] as $type) {
            $o = $this->policy->decide(FuelTransactionClassification::make($type, FuelSource::EDK_ACCOUNT));
            $this->assertSame(PersistenceDecision::ACCEPT, $o->persistence);
            $this->assertSame(KpiEligibility::NOT_ELIGIBLE, $o->kpiEligibility);
            $this->assertSame(ReviewDecision::NONE, $o->review);
        }
    }

    public function test_row12_duplicate_rejected_no_review(): void
    {
        $o = $this->policy->decide(new FuelTransactionClassification(
            TransactionType::FUEL_RECHARGE, FuelSource::EDK_CARD,
            new ValidationFindings([TechnicalFinding::DUPLICATE_TRANSACTION], [])
        ));
        $this->assertSame(PersistenceDecision::REJECT, $o->persistence);
        $this->assertSame(ReviewDecision::NONE, $o->review);
    }

    public function test_row15_malformed_rejected_with_review(): void
    {
        $o = $this->policy->decide(new FuelTransactionClassification(
            TransactionType::UNKNOWN, FuelSource::CSV,
            new ValidationFindings([TechnicalFinding::MALFORMED_ROW], [])
        ));
        $this->assertSame(PersistenceDecision::REJECT, $o->persistence);
        $this->assertSame(ReviewDecision::REQUIRED, $o->review);
    }

    public function test_row11_unknown_type_requires_review(): void
    {
        $o = $this->policy->decide(FuelTransactionClassification::make(TransactionType::UNKNOWN, FuelSource::CSV));
        $this->assertSame(PersistenceDecision::ACCEPT, $o->persistence);
        $this->assertSame(KpiEligibility::NOT_ELIGIBLE, $o->kpiEligibility);
        $this->assertSame(ReviewDecision::REQUIRED, $o->review);
    }

    public function test_row16_reject_wins_over_business_finding(): void
    {
        $o = $this->policy->decide(new FuelTransactionClassification(
            TransactionType::FUEL_RECHARGE, FuelSource::EDK_CARD,
            new ValidationFindings([TechnicalFinding::INVALID_AMOUNT], [BusinessFinding::INACTIVE_TRUCK])
        ));
        $this->assertSame(PersistenceDecision::REJECT, $o->persistence);
        $this->assertSame(KpiEligibility::NOT_ELIGIBLE, $o->kpiEligibility);
        $this->assertSame(ReviewDecision::REQUIRED, $o->review);
    }

    public function test_policy_version_is_reported(): void
    {
        $this->assertSame('v1', $this->policy->version());
    }
}
