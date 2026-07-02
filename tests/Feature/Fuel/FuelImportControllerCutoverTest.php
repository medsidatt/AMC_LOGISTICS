<?php

namespace Tests\Feature\Fuel;

use App\Models\Auth\User;
use App\Models\FuelCardTransaction;
use App\Models\Truck;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * R9 — the controller is a thin boundary over FuelImportService: preview runs the pipeline without
 * persisting; commit delegates entirely to the service. End-to-end HTTP test through the new pipeline.
 */
class FuelImportControllerCutoverTest extends TestCase
{
    use DatabaseTransactions;

    private function importer(): User
    {
        return User::query()->permission('fuel-import')->firstOrFail();
    }

    private function csv(): string
    {
        $mat = (string) Truck::where('is_active', true)->firstOrFail()->matricule;

        return implode("\n", [
            ' ID Transaction; N transaction; Date; Montant; Numero carte;  Porteur',
            "0;R9-CLEAN-1;01-Mai-2030  10:00:00;210000;R9CARD1;{$mat} ZZZUNIQUEHOLDER",
            '0;R9-UNK-1;01-Mai-2030  11:00:00;210000;R9CARD2;9999TTA1 Personne Inconnue',
            '0;R9-BAD;too;few',
        ]);
    }

    public function test_preview_runs_pipeline_without_persisting(): void
    {
        $before = FuelCardTransaction::count();

        $response = $this->actingAs($this->importer())->post('/fuel/import/edk/preview', [
            'file' => UploadedFile::fake()->createWithContent('edk.csv', $this->csv()),
            'price_per_litre' => 730,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['source', 'total_rows', 'summary' => ['accepted_rows', 'rejected_rows'], 'rows', 'token']);
        $this->assertSame(2, $response->json('summary.accepted_rows'));
        $this->assertSame(1, $response->json('summary.rejected_rows'));
        $this->assertSame($before, FuelCardTransaction::count(), 'preview must not persist anything');
    }

    public function test_commit_delegates_to_service_and_persists(): void
    {
        $importer = $this->importer();

        $token = $this->actingAs($importer)->post('/fuel/import/edk/preview', [
            'file' => UploadedFile::fake()->createWithContent('edk.csv', $this->csv()),
            'price_per_litre' => 730,
        ])->json('token');

        $this->actingAs($importer)->post('/fuel/import/edk/commit', ['token' => $token])
            ->assertRedirect();

        // Both card rows persisted (clean + unknown-truck — financial truth kept); malformed quarantined.
        $this->assertSame(1, FuelCardTransaction::where('transaction_ref', 'R9-CLEAN-1')->count());
        $unk = FuelCardTransaction::where('transaction_ref', 'R9-UNK-1')->firstOrFail();
        $this->assertNull($unk->truck_id);
        $this->assertFalse($unk->kpi_eligible);
        $this->assertSame('PENDING', $unk->review_status);
    }
}
