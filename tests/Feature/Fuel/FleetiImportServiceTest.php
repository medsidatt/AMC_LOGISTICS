<?php

namespace Tests\Feature\Fuel;

use App\Models\FleetiDailyRecord;
use App\Models\Transporter;
use App\Models\Truck;
use App\Services\Fuel\FleetiImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * FleetiImportService owns the daily-record upsert extracted from FuelImportController::commitFleeti.
 * It must be behaviour-preserving: upsert by (truck_id, record_date), writing ONLY the columns each
 * row declares via `_owned`, so importing Volume 2.0 then Carburant for the same day merges instead
 * of clobbering (import-order independent).
 */
class FleetiImportServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function freshTruck(): Truck
    {
        return Truck::create([
            'matricule' => 'FITEST-'.strtoupper(substr(uniqid('', true), -8)),
            'transporter_id' => (int) Transporter::query()->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_persist_merges_source_owned_columns_for_the_same_truck_day(): void
    {
        $service = app(FleetiImportService::class);
        $truck = $this->freshTruck();
        $date = '2026-06-15';

        // Volume 2.0 owns consumption/refuel columns.
        $r1 = $service->persist([[
            'truck_id' => $truck->id, 'date' => $date,
            '_owned' => ['kilometers', 'consumed', 'refills_volume'],
            'kilometers' => 300.0, 'consumed' => 120.0, 'refills_volume' => 150.0,
            'volume_initial' => 999.0, // present but NOT owned → must be ignored
        ]], null);

        $this->assertSame(['inserted' => 1, 'updated' => 0], $r1);
        $rec = FleetiDailyRecord::where('truck_id', $truck->id)->where('record_date', $date)->firstOrFail();
        $this->assertSame(120.0, (float) $rec->consumed);
        $this->assertSame(0.0, (float) $rec->volume_initial, 'unowned column left at default — the 999.0 input was NOT written');

        // Carburant owns tank/drains columns for the SAME day → update, merge (no clobber of consumed).
        $r2 = $service->persist([[
            'truck_id' => $truck->id, 'date' => $date,
            '_owned' => ['volume_initial', 'volume_final', 'drains_volume'],
            'volume_initial' => 800.0, 'volume_final' => 680.0, 'drains_volume' => 5.0,
            'consumed' => 111.0, // present but NOT owned → must NOT overwrite the Volume 2.0 value
        ]], null);

        $this->assertSame(['inserted' => 0, 'updated' => 1], $r2);
        $rec->refresh();
        $this->assertSame(800.0, (float) $rec->volume_initial, 'tank column merged in');
        $this->assertSame(120.0, (float) $rec->consumed, 'consumption preserved — not clobbered');
    }
}
