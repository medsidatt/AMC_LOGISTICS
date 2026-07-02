<?php

namespace Tests\Feature\Fuel;

use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\ReviewOutcome;
use App\Enums\Fuel\TransactionType;
use App\Models\Auth\User;
use App\Models\FuelCardTransaction;
use App\Models\FuelTransactionReviewEvent;
use App\Models\Truck;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * R10 — thin controller: queue renders pending, show returns proposal/effective/history, resolve
 * delegates to the service and redirects.
 */
class FuelReviewControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function importer(): User
    {
        return User::query()->permission('fuel-import')->firstOrFail();
    }

    private function pendingTx(): FuelCardTransaction
    {
        return FuelCardTransaction::create([
            'source' => FuelSource::EDK_CARD->value,
            'transaction_type' => TransactionType::FUEL_RECHARGE->value,
            'transaction_ref' => 'REV-CTRL-'.uniqid(),
            'amount_fcfa' => 210000, 'estimated_litres' => 287, 'price_per_litre' => 730,
            'occurred_at' => '2030-06-01 10:00:00', 'detected_plate' => '9999TTA1',
            'kpi_eligible' => false, 'review_status' => 'PENDING',
            'proposed_technical_findings' => [], 'proposed_business_findings' => ['UNKNOWN_TRUCK'],
            'proposed_kpi_eligible' => false, 'policy_version' => 'v1',
        ]);
    }

    public function test_queue_renders_pending_transactions(): void
    {
        $tx = $this->pendingTx();

        $this->actingAs($this->importer())->get('/fuel/review')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('fuel/Review')
                ->has('records.data')
                ->has('outcomes'));

        $this->assertSame('PENDING', $tx->fresh()->review_status);
    }

    public function test_show_returns_proposal_effective_and_history(): void
    {
        $tx = $this->pendingTx();

        $this->actingAs($this->importer())->get("/fuel/review/{$tx->id}")
            ->assertOk()
            ->assertJsonPath('proposal.business_findings', ['UNKNOWN_TRUCK'])
            ->assertJsonPath('proposal.policy_version', 'v1')
            ->assertJsonPath('effective.review_status', 'PENDING')
            ->assertJsonStructure(['record', 'effective', 'proposal', 'history']);
    }

    public function test_resolve_delegates_and_redirects(): void
    {
        $truck = Truck::where('is_active', true)->firstOrFail();
        $tx = $this->pendingTx();

        $this->actingAs($this->importer())
            ->post("/fuel/review/{$tx->id}", ['outcome' => ReviewOutcome::RE_ATTRIBUTED->value, 'truck_id' => $truck->id, 'note' => 'ok'])
            ->assertRedirect();

        $tx->refresh();
        $this->assertSame('RESOLVED', $tx->review_status);
        $this->assertSame((int) $truck->id, (int) $tx->truck_id);
        $this->assertTrue($tx->kpi_eligible);
        $this->assertSame(1, FuelTransactionReviewEvent::where('fuel_card_transaction_id', $tx->id)->count());
    }

    public function test_resolve_validates_outcome(): void
    {
        $tx = $this->pendingTx();
        $this->actingAs($this->importer())
            ->post("/fuel/review/{$tx->id}", ['outcome' => 'NOT_A_REAL_OUTCOME'])
            ->assertSessionHasErrors('outcome');
    }
}
