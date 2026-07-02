<?php

namespace Tests\Feature\Fuel;

use App\Models\FuelCardTransaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The historical importer is a thin CLI over FuelImportService — it must import through the accepted
 * pipeline (so ClassificationPolicy still decides) and never invent outcomes. Business classification
 * itself is covered exhaustively by FuelImportServiceTest / ClassificationPolicyTest; this only proves
 * the command wires files into that pipeline and reports.
 */
class ImportEdkHistoryCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_imports_edk_csv_through_the_pipeline(): void
    {
        $ref = 'CMD-'.uniqid();
        $csv = "ID Transaction;N transaction;Date;Montant;Numero carte;Porteur\n"
            ."0;{$ref};13-Mai-2026 22:12:23;210000;CARD001;CHAUFFEUR 9999TTA1\n"
            ."Montant Total;;;210000\n";
        $path = tempnam(sys_get_temp_dir(), 'edk').'.csv';
        file_put_contents($path, $csv);

        $this->artisan('fuel:import-edk', ['files' => [$path], '--price' => 730])
            ->assertSuccessful();

        $tx = FuelCardTransaction::where('transaction_ref', $ref)->first();
        $this->assertNotNull($tx, 'the command persisted the row via the pipeline');
        $this->assertSame('EDK_CARD', $tx->source->value);
        $this->assertSame(210000.0, (float) $tx->amount_fcfa, 'financial amount preserved verbatim');

        @unlink($path);
    }
}
