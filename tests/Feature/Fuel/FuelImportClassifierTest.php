<?php

namespace Tests\Feature\Fuel;

use App\Domain\Fuel\Classification\FuelImportClassifier;
use App\Domain\Fuel\Classification\FuelImportReference;
use App\Domain\Fuel\ClassificationPolicy;
use App\Domain\Fuel\Parsing\ParsedFuelImportFile;
use App\Domain\Fuel\Parsing\ParsedFuelImportRow;
use App\Domain\Fuel\Parsing\ParseError;
use App\Domain\Fuel\ValueObjects\FuelTransactionClassification;
use App\Enums\Fuel\BusinessFinding;
use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\KpiEligibility;
use App\Enums\Fuel\PersistenceDecision;
use App\Enums\Fuel\ReviewDecision;
use App\Enums\Fuel\TechnicalFinding;
use App\Enums\Fuel\TransactionType;
use Tests\TestCase;

/**
 * R7 — the classifier derives business FACTS only. Pure: no DB, no seeding (reference is hand-built),
 * so it also proves the classifier needs no database. Includes the classifier→ClassificationPolicy seam.
 */
class FuelImportClassifierTest extends TestCase
{
    private FuelImportClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new FuelImportClassifier;
    }

    private function row(array $o = []): ParsedFuelImportRow
    {
        return new ParsedFuelImportRow(
            lineNumber: $o['line'] ?? 1,
            rawLine: $o['raw'] ?? '0;TX-1;01-Juin-2030 10:00:00;210000;C1;6077TTA1',
            source: $o['source'] ?? FuelSource::EDK_CARD,
            transactionRef: array_key_exists('ref', $o) ? $o['ref'] : 'TX-1',
            occurredAt: array_key_exists('occurredAt', $o) ? $o['occurredAt'] : '2030-06-01 10:00:00',
            occurredAtRaw: '01-Juin-2030 10:00:00',
            amount: array_key_exists('amount', $o) ? $o['amount'] : 210000.0,
            amountRaw: '210000',
            cardNumber: array_key_exists('card', $o) ? $o['card'] : null,
            normalizedRegistration: array_key_exists('reg', $o) ? $o['reg'] : '6077TTA1',
            holderRaw: array_key_exists('holder', $o) ? $o['holder'] : null,
            mode: array_key_exists('mode', $o) ? $o['mode'] : null,
            note: $o['note'] ?? null,
            errors: $o['errors'] ?? [],
        );
    }

    private function ref(array $o = []): FuelImportReference
    {
        return new FuelImportReference(
            $o['matriculeMap'] ?? ['6077TTA1' => ['id' => 1, 'active' => true]],
            $o['drivers'] ?? [],
            $o['truckDriverIds'] ?? [],
            $o['cardOwner'] ?? [],
            $o['existingRefs'] ?? [],
        );
    }

    /** @return list<string> */
    private function tech(FuelTransactionClassification $c): array
    {
        return array_map(fn ($f) => $f->value, $c->findings->technical);
    }

    /** @return list<string> */
    private function biz(FuelTransactionClassification $c): array
    {
        return array_map(fn ($f) => $f->value, $c->findings->business);
    }

    public function test_clean_card_recharge_has_no_findings(): void
    {
        $c = $this->classifier->classify($this->row(['card' => 'C1']), $this->ref());
        $this->assertSame(TransactionType::FUEL_RECHARGE, $c->type);
        $this->assertSame(FuelSource::EDK_CARD, $c->source);
        $this->assertTrue($c->findings->isEmpty());
    }

    public function test_unknown_truck_when_registration_not_in_fleet(): void
    {
        $c = $this->classifier->classify($this->row(['reg' => '9999TTA1']), $this->ref());
        $this->assertSame([BusinessFinding::UNKNOWN_TRUCK->value], $this->biz($c));
    }

    public function test_inactive_truck_when_resolved_but_not_active(): void
    {
        $c = $this->classifier->classify(
            $this->row(['reg' => '6066TTA1']),
            $this->ref(['matriculeMap' => ['6066TTA1' => ['id' => 2, 'active' => false]]])
        );
        $this->assertSame([BusinessFinding::INACTIVE_TRUCK->value], $this->biz($c));
    }

    public function test_card_mismatch_when_card_owned_by_another_truck(): void
    {
        $c = $this->classifier->classify(
            $this->row(['card' => 'CARD-XYZ']),
            $this->ref(['cardOwner' => ['CARD-XYZ' => 99]]) // owned by truck 99, row resolves to truck 1
        );
        $this->assertContains(BusinessFinding::CARD_MISMATCH->value, $this->biz($c));
    }

    public function test_driver_mismatch_when_detected_driver_not_assigned(): void
    {
        $c = $this->classifier->classify(
            $this->row(['holder' => 'Salif Niang 6077TTA1']),
            $this->ref(['drivers' => [['id' => 5, 'name' => 'Salif Niang']], 'truckDriverIds' => [1 => [6]]])
        );
        $this->assertContains(BusinessFinding::DRIVER_MISMATCH->value, $this->biz($c));
    }

    public function test_multiple_business_findings_accumulate(): void
    {
        $c = $this->classifier->classify(
            $this->row(['reg' => '6066TTA1', 'card' => 'CARD-XYZ']),
            $this->ref(['matriculeMap' => ['6066TTA1' => ['id' => 2, 'active' => false]], 'cardOwner' => ['CARD-XYZ' => 99]])
        );
        $this->assertContains(BusinessFinding::INACTIVE_TRUCK->value, $this->biz($c));
        $this->assertContains(BusinessFinding::CARD_MISMATCH->value, $this->biz($c));
    }

    public function test_account_recharge_type_and_no_truck_findings(): void
    {
        $c = $this->classifier->classify(
            $this->row(['source' => FuelSource::EDK_ACCOUNT, 'mode' => 'Rechargement par Espèces', 'reg' => null, 'card' => null]),
            $this->ref()
        );
        $this->assertSame(TransactionType::ACCOUNT_RECHARGE, $c->type);
        $this->assertSame([], $this->biz($c), 'account movements are not truck-attributed → no UNKNOWN_TRUCK');
        $this->assertTrue($c->findings->isEmpty());
    }

    public function test_account_transfer_type(): void
    {
        $c = $this->classifier->classify(
            $this->row(['source' => FuelSource::EDK_ACCOUNT, 'mode' => 'Transfert carte vers Compte', 'reg' => null]),
            $this->ref()
        );
        $this->assertSame(TransactionType::ACCOUNT_TRANSFER, $c->type);
    }

    public function test_unknown_account_mode_is_unknown_type(): void
    {
        $c = $this->classifier->classify(
            $this->row(['source' => FuelSource::EDK_ACCOUNT, 'mode' => 'Quelque chose', 'reg' => null]),
            $this->ref()
        );
        $this->assertSame(TransactionType::UNKNOWN, $c->type);
    }

    public function test_invalid_date_and_amount_are_technical_findings(): void
    {
        $c = $this->classifier->classify($this->row(['occurredAt' => null, 'amount' => 0.0]), $this->ref());
        $this->assertContains(TechnicalFinding::INVALID_DATE->value, $this->tech($c));
        $this->assertContains(TechnicalFinding::INVALID_AMOUNT->value, $this->tech($c));

        $neg = $this->classifier->classify($this->row(['amount' => -5.0]), $this->ref());
        $this->assertContains(TechnicalFinding::INVALID_AMOUNT->value, $this->tech($neg));
    }

    public function test_malformed_row_short_circuits_to_single_finding(): void
    {
        $c = $this->classifier->classify(
            $this->row(['errors' => [new ParseError(ParseError::MALFORMED_ROW, 'x')], 'occurredAt' => null, 'amount' => null]),
            $this->ref()
        );
        $this->assertSame([TechnicalFinding::MALFORMED_ROW->value], $this->tech($c));
        $this->assertSame([], $this->biz($c), 'no business facts derived from a structurally broken row');
    }

    public function test_missing_transaction_ref_maps_to_malformed(): void
    {
        $c = $this->classifier->classify(
            $this->row(['ref' => null, 'errors' => [new ParseError(ParseError::MISSING_TRANSACTION_REF, 'x')]]),
            $this->ref()
        );
        $this->assertContains(TechnicalFinding::MALFORMED_ROW->value, $this->tech($c));
    }

    public function test_duplicate_against_existing_refs(): void
    {
        $c = $this->classifier->classify($this->row(['ref' => 'R1']), $this->ref(['existingRefs' => ['R1' => true]]));
        $this->assertContains(TechnicalFinding::DUPLICATE_TRANSACTION->value, $this->tech($c));
    }

    public function test_batch_duplicate_flags_the_second_occurrence(): void
    {
        $file = new ParsedFuelImportFile(FuelSource::EDK_CARD, [
            $this->row(['ref' => 'SAME']),
            $this->row(['ref' => 'SAME']),
        ]);
        $results = $this->classifier->classifyFile($file, $this->ref());

        $this->assertNotContains(TechnicalFinding::DUPLICATE_TRANSACTION->value, $this->tech($results[0]));
        $this->assertContains(TechnicalFinding::DUPLICATE_TRANSACTION->value, $this->tech($results[1]));
    }

    public function test_classifier_output_is_classification_not_a_policy_outcome(): void
    {
        $c = $this->classifier->classify($this->row(), $this->ref());
        $this->assertInstanceOf(FuelTransactionClassification::class, $c);
    }

    public function test_classifier_output_feeds_classification_policy_correctly(): void
    {
        $policy = new ClassificationPolicy;

        // FUEL_RECHARGE + UNKNOWN_TRUCK → ACCEPT / NOT_ELIGIBLE / REQUIRED (the AA463AQ case)
        $o = $policy->decide($this->classifier->classify($this->row(['reg' => '9999TTA1']), $this->ref()));
        $this->assertSame(PersistenceDecision::ACCEPT, $o->persistence);
        $this->assertSame(KpiEligibility::NOT_ELIGIBLE, $o->kpiEligibility);
        $this->assertSame(ReviewDecision::REQUIRED, $o->review);

        // clean FUEL_RECHARGE → ELIGIBLE / NONE
        $o2 = $policy->decide($this->classifier->classify($this->row(), $this->ref()));
        $this->assertSame(KpiEligibility::ELIGIBLE, $o2->kpiEligibility);
        $this->assertSame(ReviewDecision::NONE, $o2->review);

        // ACCOUNT_TRANSFER clean → ACCEPT / NOT_ELIGIBLE / NONE
        $o3 = $policy->decide($this->classifier->classify(
            $this->row(['source' => FuelSource::EDK_ACCOUNT, 'mode' => 'Transfert carte vers Compte', 'reg' => null]),
            $this->ref()
        ));
        $this->assertSame(PersistenceDecision::ACCEPT, $o3->persistence);
        $this->assertSame(KpiEligibility::NOT_ELIGIBLE, $o3->kpiEligibility);
        $this->assertSame(ReviewDecision::NONE, $o3->review);

        // duplicate → REJECT
        $o4 = $policy->decide($this->classifier->classify($this->row(['ref' => 'R1']), $this->ref(['existingRefs' => ['R1' => true]])));
        $this->assertSame(PersistenceDecision::REJECT, $o4->persistence);
    }
}
