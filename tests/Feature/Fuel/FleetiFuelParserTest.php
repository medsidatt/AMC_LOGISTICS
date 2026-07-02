<?php

namespace Tests\Feature\Fuel;

use App\Models\Truck;
use App\Services\Fuel\FleetiFuelParser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * F1.5 characterization — the importer must ingest BOTH current Fleeti exports into the canonical
 * FleetiDailyRecord shape:
 *   - "Volume de carburant 2.0" (6 cols): refuel + consumption only — must NOT carry tank/drain keys.
 *   - "Carburant" (12 cols): tank telemetry + drains — must carry volume_initial/final + drains.
 * The retired single "Rapport de carburant" export shared the 12-col layout, so it stays covered.
 *
 * DatabaseTransactions keeps the dev DB clean; fixtures are generated as real .xlsx files in temp.
 */
class FleetiFuelParserTest extends TestCase
{
    use DatabaseTransactions;

    private Truck $truck;

    protected function setUp(): void
    {
        parent::setUp();

        // Reuse a seeded AMC truck whose matricule matches the NNNN…TTA1 plate the parser keys on.
        $truck = Truck::where('is_active', true)->get()->first(function (Truck $t) {
            $norm = strtoupper(preg_replace('/[\s\-]+/', '', (string) $t->matricule));

            return preg_match('/\d{4}/', $norm) && str_contains($norm, 'TTA1');
        });

        if (! $truck) {
            $this->markTestSkipped('No active TTA1-plated truck seeded to characterize the parser against.');
        }

        $this->truck = $truck;
    }

    public function test_volume_de_carburant_2_export_yields_consumption_only_rows(): void
    {
        $path = $this->makeWorkbook('Volume de carburant 2.0', [
            // Détail par dates — single header row, 6 columns.
            ['Détail par dates'],
            ['Date', 'kilometrage, km', 'Nombre de Ravitaillements', 'Volume, L', 'Consommé, L', 'Consommation, L/100km'],
            ['1 juin 2026', 271.93, 0, 0, 165.57, 60.89],
            ['4 juin 2026', 157.95, 2, 319.86, 92.74, 58.71],
            ['8 juin 2026', 0, 0, 0, 0, 0], // no activity → skipped
        ]);

        $result = (new FleetiFuelParser)->parse($path);

        $this->assertSame(2, $result['totals']['count_rows'], 'zero-activity day must be skipped');
        $this->assertSame(1, $result['totals']['count_trucks']);

        $row = collect($result['valid'])->firstWhere('date', '2026-06-04');
        $this->assertNotNull($row);
        $this->assertSame($this->truck->id, $row['truck_id']);
        $this->assertSame(157.95, $row['kilometers']);
        $this->assertSame(92.74, $row['consumed']);
        $this->assertSame(2, $row['refills_count']);
        $this->assertSame(319.86, $row['refills_volume']);

        // Volume 2.0 does NOT own tank/drain columns — they must be absent so a later Carburant
        // import can fill them without this import having zeroed them.
        $this->assertArrayNotHasKey('volume_initial', $row);
        $this->assertArrayNotHasKey('volume_final', $row);
        $this->assertArrayNotHasKey('drains_volume', $row);
        $this->assertArrayNotHasKey('drains_count', $row);

        @unlink($path);
    }

    public function test_carburant_export_yields_tank_and_drain_rows(): void
    {
        $path = $this->makeWorkbook('Carburant', [
            // Détail par dates — two header rows (group + sub), 12 columns incl. "Volume initial".
            ['Détail par dates'],
            ['Date', 'GPS', 'GPS', 'GPS', 'capteur', 'capteur', 'capteur', 'capteur', 'Remplissages', 'Remplissages', 'Vidages', 'Vidages'],
            ['Date', 'kilometrage, km', 'Consommation par calcul, L', 'Consommation par calcul, L/100km', 'Volume initial, L', 'Volume final, L', 'Consommé, L', 'Consommation, L/100km', 'Nombre', 'Volume, L', 'Nombre', 'Volume, L'],
            ['1 juin 2026', 273.14, null, null, 357.94, 192.37, 142.81, 52.29, 0, 0, 1, 22.76],
        ]);

        $result = (new FleetiFuelParser)->parse($path);

        $this->assertSame(1, $result['totals']['count_rows']);

        $row = collect($result['valid'])->firstWhere('date', '2026-06-01');
        $this->assertNotNull($row);
        $this->assertSame($this->truck->id, $row['truck_id']);
        $this->assertSame(273.14, $row['kilometers']);
        $this->assertSame(142.81, $row['consumed']);

        // Carburant DOES own tank + drains.
        $this->assertSame(357.94, $row['volume_initial']);
        $this->assertSame(192.37, $row['volume_final']);
        $this->assertSame(1, $row['drains_count']);
        $this->assertSame(22.76, $row['drains_volume']);

        @unlink($path);
    }

    /**
     * Build a minimal Fleeti-shaped workbook: a "Résumé" sheet (must be ignored), a per-truck
     * header/chart sheet ("{$title}: {matricule} / …" — sets the truck), then the detail rows.
     *
     * @param  array<int, array<int, mixed>>  $detailRows
     */
    private function makeWorkbook(string $title, array $detailRows): string
    {
        $spreadsheet = new Spreadsheet;

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Résumé');
        $summary->setCellValue('A1', "{$title}: Résumé");
        $summary->setCellValue('A2', 'Pour la période : 1 juin 2026 - 30 juin 2026');

        $header = $spreadsheet->createSheet();
        $header->setTitle($this->truck->matricule . ' X');
        $header->setCellValue('A1', "{$title}: {$this->truck->matricule} / FAW J6P-420");

        $detail = $spreadsheet->createSheet();
        $detail->setTitle($this->truck->matricule . ' - 2');
        foreach ($detailRows as $r => $cells) {
            foreach ($cells as $c => $value) {
                $detail->setCellValue(Coordinate::stringFromColumnIndex($c + 1) . ($r + 1), $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'fleeti_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
