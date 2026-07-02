<?php

namespace Tests\Feature\Fuel;

use App\Domain\Fuel\Classification\FuelImportReferenceReader;
use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TransactionType;
use App\Models\FuelCardTransaction;
use App\Models\Truck;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * R7 — the query-only reader builds a usable reference snapshot from live data. Light DB test.
 */
class FuelImportReferenceReaderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reader_resolves_truck_existing_ref_and_card_owner(): void
    {
        $truck = Truck::where('is_active', true)->firstOrFail();
        $normalized = strtoupper(preg_replace('/[\s\-]+/', '', (string) $truck->matricule));

        FuelCardTransaction::create([
            'source' => FuelSource::EDK_CARD->value,
            'transaction_type' => TransactionType::FUEL_RECHARGE->value,
            'truck_id' => $truck->id,
            'transaction_ref' => 'READER-REF-1',
            'card_number' => 'READER-CARD-1',
            'amount_fcfa' => 210000,
            'estimated_litres' => 287,
            'price_per_litre' => 730,
            'occurred_at' => '2030-06-01 10:00:00',
        ]);

        $reference = (new FuelImportReferenceReader)->read();

        $resolved = $reference->truckFor($normalized);
        $this->assertNotNull($resolved);
        $this->assertSame((int) $truck->id, $resolved['id']);
        $this->assertTrue($resolved['active']);

        $this->assertNull($reference->truckFor('0000ZZZZ'), 'a plate not in the fleet resolves to null');
        $this->assertTrue($reference->refAlreadyExists('READER-REF-1'));
        $this->assertFalse($reference->refAlreadyExists('NON-EXISTENT'));
        $this->assertSame((int) $truck->id, $reference->cardOwnerTruckId('READER-CARD-1'));
    }
}
