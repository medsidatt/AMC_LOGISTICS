<?php

namespace Tests\Feature\Fuel;

use App\Domain\Fuel\ValueObjects\FuelTransactionClassification;
use App\Domain\Fuel\ValueObjects\ValidationFindings;
use App\Enums\Fuel\BusinessFinding;
use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TechnicalFinding;
use App\Enums\Fuel\TransactionType;
use InvalidArgumentException;
use Tests\TestCase;

/** R1/R2 — enum behaviour + value-object invariants (immutability, dedupe, category safety). */
class FuelClassificationValueObjectsTest extends TestCase
{
    public function test_validation_findings_dedupe_and_codes(): void
    {
        $f = new ValidationFindings(
            [TechnicalFinding::INVALID_DATE, TechnicalFinding::INVALID_DATE],
            [BusinessFinding::UNKNOWN_TRUCK, BusinessFinding::UNKNOWN_TRUCK]
        );
        $this->assertCount(1, $f->technical);
        $this->assertCount(1, $f->business);
        $this->assertSame(['INVALID_DATE'], $f->technicalCodes());
        $this->assertSame(['UNKNOWN_TRUCK'], $f->businessCodes());
        $this->assertFalse($f->isEmpty());
        $this->assertTrue(ValidationFindings::none()->isEmpty());
    }

    public function test_findings_aggregate_reject_and_review(): void
    {
        $dup = new ValidationFindings([TechnicalFinding::DUPLICATE_TRANSACTION]);
        $this->assertTrue($dup->hasRejectingFinding());
        $this->assertFalse($dup->hasReviewFinding());

        $bad = new ValidationFindings([TechnicalFinding::INVALID_DATE]);
        $this->assertTrue($bad->hasRejectingFinding());
        $this->assertTrue($bad->hasReviewFinding());

        $biz = new ValidationFindings([], [BusinessFinding::CARD_MISMATCH]);
        $this->assertFalse($biz->hasRejectingFinding());
        $this->assertTrue($biz->hasReviewFinding());
    }

    public function test_findings_are_immutable_with_methods_return_new(): void
    {
        $a = ValidationFindings::none();
        $b = $a->withTechnical(TechnicalFinding::INVALID_AMOUNT);
        $this->assertTrue($a->isEmpty());
        $this->assertFalse($b->isEmpty());
        $this->assertNotSame($a, $b);
    }

    public function test_findings_reject_wrong_category_element(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // A BusinessFinding passed into the technical slot must be rejected.
        new ValidationFindings([BusinessFinding::UNKNOWN_TRUCK]);
    }

    public function test_transaction_type_capabilities(): void
    {
        $this->assertTrue(TransactionType::FUEL_RECHARGE->isKpiCapable());
        foreach ([TransactionType::ACCOUNT_RECHARGE, TransactionType::ACCOUNT_TRANSFER, TransactionType::REVERSAL, TransactionType::UNKNOWN] as $t) {
            $this->assertFalse($t->isKpiCapable(), $t->value.' must not be KPI-capable');
        }
        $this->assertTrue(TransactionType::UNKNOWN->requiresReviewWhenClean());
        $this->assertFalse(TransactionType::FUEL_RECHARGE->requiresReviewWhenClean());
    }

    public function test_technical_finding_flags(): void
    {
        foreach (TechnicalFinding::cases() as $t) {
            $this->assertTrue($t->forcesReject(), $t->value.' is fatal in v1');
        }
        $this->assertFalse(TechnicalFinding::DUPLICATE_TRANSACTION->forcesReview());
        foreach ([TechnicalFinding::INVALID_DATE, TechnicalFinding::INVALID_AMOUNT, TechnicalFinding::MALFORMED_ROW] as $t) {
            $this->assertTrue($t->forcesReview());
        }
    }

    public function test_business_finding_flags(): void
    {
        foreach (BusinessFinding::cases() as $b) {
            $this->assertTrue($b->forcesReview(), $b->value.' requires review in v1');
        }
    }

    public function test_classification_make_defaults_to_no_findings(): void
    {
        $c = FuelTransactionClassification::make(TransactionType::FUEL_RECHARGE, FuelSource::EDK_CARD);
        $this->assertSame(TransactionType::FUEL_RECHARGE, $c->type);
        $this->assertSame(FuelSource::EDK_CARD, $c->source);
        $this->assertTrue($c->findings->isEmpty());
    }
}
