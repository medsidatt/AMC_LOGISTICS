<?php

namespace Tests\Feature\Fuel;

use App\Models\FleetiDailyRecord;
use App\Models\Truck;
use App\Services\Fuel\FleetiFuelParser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Regression guard for the Fuel Import Stabilization phase.
 *   - Blocker #3: Volume 2.0 and Carburant both wrote consumption/refuel → import order changed data.
 * (Blocker #1 — the EDK "Jui" June date — is now covered by EdkImportParserTest, R6.)
 */
class FuelStabilizationTest extends TestCase
{
    use DatabaseTransactions;

    private Truck $truck;

    protected function setUp(): void
    {
        parent::setUp();
        $truck = Truck::where('is_active', true)->get()->first(function (Truck $t) {
            $norm = strtoupper(preg_replace('/[\s\-]+/', '', (string) $t->matricule));

            return preg_match('/\d{4}/', $norm) && str_contains($norm, 'TTA1');
        });
        if (! $truck) {
            $this->markTestSkipped('No active TTA1-plated truck seeded.');
        }
        $this->truck = $truck;
    }

    /** Blocker #3 — importing Volume 2.0 + Carburant yields the same row regardless of order. */
    public function test_volume2_and_carburant_ownership_is_import_order_independent(): void
    {
        // Volume 2.0 says consumed=100 / refills=40 ; Carburant says consumed=999 (NOT owned) but volInit=300 / drains=12.
        $volume2 = $this->workbook('Volume de carburant 2.0', false, [
            ['Détail par dates'],
            ['Date', 'kilometrage, km', 'Nombre de Ravitaillements', 'Volume, L', 'Consommé, L', 'Consommation, L/100km'],
            ['1 juin 2026', 200, 1, 40, 100, 50],
        ]);
        $carburant = $this->workbook('Carburant', true, [
            ['Détail par dates'],
            ['Date', 'GPS', 'GPS', 'GPS', 'capteur', 'capteur', 'capteur', 'capteur', 'Remplissages', 'Remplissages', 'Vidages', 'Vidages'],
            ['Date', 'kilometrage, km', 'x', 'x', 'Volume initial, L', 'Volume final, L', 'Consommé, L', 'Consommation, L/100km', 'Nombre', 'Volume, L', 'Nombre', 'Volume, L'],
            ['1 juin 2026', 210, null, null, 300, 150, 999, 60, 0, 0, 1, 12],
        ]);

        $parser = new FleetiFuelParser;
        $vRows = $parser->parse($volume2)['valid'];
        $cRows = $parser->parse($carburant)['valid'];

        $resultFor = function (array $first, array $second) {
            FleetiDailyRecord::where('truck_id', $this->truck->id)->delete();
            $this->commit($first);
            $this->commit($second);

            return FleetiDailyRecord::where('truck_id', $this->truck->id)->where('record_date', '2026-06-01')->first();
        };

        $a = $resultFor($vRows, $cRows);   // Volume 2.0 then Carburant
        $b = $resultFor($cRows, $vRows);   // Carburant then Volume 2.0

        foreach (['A: vol→carb' => $a, 'B: carb→vol' => $b] as $label => $row) {
            $this->assertNotNull($row, "$label produced a row");
            $this->assertSame(100.0, (float) $row->consumed, "$label: consumed owned by Volume 2.0 (not Carburant's 999)");
            $this->assertSame(40.0, (float) $row->refills_volume, "$label: refills owned by Volume 2.0");
            $this->assertSame(300.0, (float) $row->volume_initial, "$label: tank owned by Carburant");
            $this->assertSame(12.0, (float) $row->drains_volume, "$label: drains owned by Carburant");
        }

        $this->assertEquals(
            [$a->consumed, $a->refills_volume, $a->volume_initial, $a->drains_volume],
            [$b->consumed, $b->refills_volume, $b->volume_initial, $b->drains_volume],
            'stored business data must be identical regardless of import order'
        );
    }

    /** Mirrors FuelImportController::commitFleeti (persists only the parser-declared `_owned` columns). */
    private function commit(array $rows): void
    {
        $all = ['kilometers', 'volume_initial', 'volume_final', 'consumed', 'consumed_per_100km', 'refills_count', 'refills_volume', 'drains_count', 'drains_volume'];
        foreach ($rows as $row) {
            $owned = $row['_owned'] ?? $all;
            $payload = [];
            foreach ($owned as $k) { if (array_key_exists($k, $row)) $payload[$k] = $row[$k]; }
            $e = FleetiDailyRecord::where('truck_id', $row['truck_id'])->where('record_date', $row['date'])->first();
            if ($e) { $e->update($payload); }
            else { FleetiDailyRecord::create(array_merge($payload, ['truck_id' => $row['truck_id'], 'record_date' => $row['date']])); }
        }
    }

    /** @param array<int, array<int, mixed>> $detailRows */
    private function workbook(string $title, bool $tank, array $detailRows): string
    {
        $s = new Spreadsheet;
        $s->getActiveSheet()->setTitle('Résumé')->setCellValue('A1', "{$title}: Résumé");
        $s->createSheet()->setTitle($this->truck->matricule . ' X')->setCellValue('A1', "{$title}: {$this->truck->matricule} / FAW");
        $d = $s->createSheet();
        $d->setTitle($this->truck->matricule . ' - 2');
        foreach ($detailRows as $r => $cells) {
            foreach ($cells as $c => $v) {
                $d->setCellValue(Coordinate::stringFromColumnIndex($c + 1) . ($r + 1), $v);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'fl_') . '.xlsx';
        (new Xlsx($s))->save($path);
        $s->disconnectWorksheets();

        return $path;
    }
}
