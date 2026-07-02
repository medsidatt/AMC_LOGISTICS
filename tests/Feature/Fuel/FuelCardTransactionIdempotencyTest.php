<?php

namespace Tests\Feature\Fuel;

use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TransactionType;
use App\Models\FuelCardTransaction;
use App\Models\Truck;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Persistence invariant (unchanged across R4/R5): importing the same transaction twice must never
 * create duplicate financial records. The mechanism changed from a composite unique
 * (transaction_id, truck_id) to a GLOBAL unique on `transaction_ref` — this test tracks that change.
 *
 * Persistence layer only: exercises the model + DB constraint, not parser/validator/policy/controller.
 * DatabaseTransactions keeps the dev DB clean.
 */
class FuelCardTransactionIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    private Truck $truck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->truck = Truck::where('is_active', true)->firstOrFail();
    }

    /** @return array<string,mixed> */
    private function attrs(array $overrides = []): array
    {
        return array_merge([
            'source' => FuelSource::EDK_CARD->value,
            'transaction_type' => TransactionType::FUEL_RECHARGE->value,
            'truck_id' => $this->truck->id,
            'transaction_ref' => 'IDEMPOTENT-REF-1',
            'amount_fcfa' => 210000,
            'estimated_litres' => 287.67,
            'price_per_litre' => 730,
            'occurred_at' => '2030-06-01 11:47:26',
        ], $overrides);
    }

    public function test_duplicate_transaction_ref_violates_global_unique_even_across_trucks(): void
    {
        $other = Truck::where('is_active', true)->where('id', '!=', $this->truck->id)->firstOrFail();

        FuelCardTransaction::create($this->attrs());

        // Same transaction_ref on a DIFFERENT truck must still be rejected — uniqueness is now global,
        // not composite on (ref, truck).
        $this->expectException(QueryException::class);
        FuelCardTransaction::create($this->attrs(['truck_id' => $other->id]));
    }

    public function test_only_one_financial_record_survives_a_duplicate_attempt(): void
    {
        FuelCardTransaction::create($this->attrs());

        try {
            FuelCardTransaction::create($this->attrs(['amount_fcfa' => 999999]));
        } catch (QueryException $e) {
            // expected — the duplicate insert is rejected by the unique constraint
        }

        $this->assertSame(1, FuelCardTransaction::where('transaction_ref', 'IDEMPOTENT-REF-1')->count());
    }

    public function test_reimport_via_upsert_updates_in_place_not_duplicates(): void
    {
        $now = now();
        $row = fn (float $litres) => [array_merge($this->attrs(), [
            'estimated_litres' => $litres, 'created_at' => $now, 'updated_at' => $now,
        ])];

        $upsert = fn (array $rows) => FuelCardTransaction::upsert(
            $rows,
            ['transaction_ref'],
            ['amount_fcfa', 'estimated_litres', 'occurred_at', 'updated_at']
        );

        $upsert($row(287.67));
        $upsert($row(300.00)); // same transaction_ref → update, not a second row

        $matches = FuelCardTransaction::where('transaction_ref', 'IDEMPOTENT-REF-1')->get();

        $this->assertCount(1, $matches, 'a repeated transaction must not create a duplicate financial record');
        $this->assertSame(300.00, (float) $matches->first()->estimated_litres, 'the second import updates in place');
    }
}
