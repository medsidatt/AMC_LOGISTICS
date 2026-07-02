<?php

namespace App\Console\Commands;

use App\Models\Auth\User;
use App\Models\FleetSetting;
use App\Models\FuelImportRejection;
use App\Services\Fuel\FuelImportService;
use Illuminate\Console\Command;

/**
 * Historical/bulk EDK importer — a thin CLI entrypoint over the ACCEPTED import pipeline
 * ({@see FuelImportService::import()} = Parser → Reference → Classifier → ClassificationPolicy →
 * Persistence). It adds NO business logic and makes NO decisions: the frozen ClassificationPolicy
 * still owns every persistence/KPI/review outcome. Idempotent via the global-unique transaction_ref,
 * so re-running is safe. Rejected rows are quarantined (never lost) and reported here.
 */
class ImportEdkHistory extends Command
{
    protected $signature = 'fuel:import-edk
        {files* : One or more EDK CSV export files (card and/or account)}
        {--price= : Price per litre in FCFA (defaults to the current FleetSetting)}
        {--user= : imported_by user id (defaults to the first user)}';

    protected $description = 'Import historical EDK card/account CSV exports through the existing FuelImportService pipeline.';

    public function handle(FuelImportService $service): int
    {
        $price = $this->option('price') !== null
            ? (float) $this->option('price')
            : (float) FleetSetting::current()->price_per_litre;

        $userId = $this->option('user') !== null
            ? (int) $this->option('user')
            : (User::query()->value('id') !== null ? (int) User::query()->value('id') : null);

        $this->info("EDK historical import — price/litre={$price} FCFA, imported_by=".($userId ?? 'system'));

        $summary = [];
        foreach ($this->argument('files') as $path) {
            if (! is_file($path)) {
                $this->error("File not found: {$path}");
                $summary[] = [basename($path), '—', 'MISSING', '', ''];

                continue;
            }

            $contents = file_get_contents($path);
            $batch = $service->import($contents, $price, basename($path), $userId);

            $summary[] = [
                basename($path),
                $batch->source,
                (int) $batch->total_rows,
                (int) $batch->accepted_rows,
                (int) $batch->rejected_rows,
            ];

            $this->reportRejections($batch->id);
        }

        $this->newLine();
        $this->table(['File', 'Source', 'Total', 'Accepted', 'Rejected'], $summary);

        return self::SUCCESS;
    }

    /** Report exactly why each row was quarantined (never silently dropped). */
    private function reportRejections(int $batchId): void
    {
        $rejections = FuelImportRejection::where('fuel_import_batch_id', $batchId)->get();
        if ($rejections->isEmpty()) {
            return;
        }

        $this->warn("  {$rejections->count()} row(s) quarantined in this batch:");
        foreach ($rejections as $r) {
            $findings = implode(', ', $r->technical_findings ?? []);
            $this->line("    line {$r->line_number} · ref=".($r->transaction_ref ?? '—')." · {$findings} · {$r->reason_summary}");
        }
    }
}
