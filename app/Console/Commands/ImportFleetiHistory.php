<?php

namespace App\Console\Commands;

use App\Models\Auth\User;
use App\Services\Fuel\FleetiImportService;
use Illuminate\Console\Command;

/**
 * Historical/bulk Fleeti importer — a thin CLI over the ACCEPTED Fleeti pipeline
 * ({@see FleetiImportService} = FleetiFuelParser → source-owned upsert), the SAME path the UI
 * commit uses. It adds no business logic: the parser's format-driven `_owned` ownership keeps
 * importing Volume 2.0 + Carburant for the same (truck, date) deterministic and order-independent.
 * Upsert-by-(truck,date) makes re-running safe.
 */
class ImportFleetiHistory extends Command
{
    protected $signature = 'fuel:import-fleeti
        {files* : One or more Fleeti XLSX exports (Volume 2.0, Carburant, or legacy Rapport)}
        {--user= : imported_by user id (defaults to the first user)}';

    protected $description = 'Import historical Fleeti fuel workbooks through the existing FleetiImportService pipeline.';

    public function handle(FleetiImportService $service): int
    {
        $userId = $this->option('user') !== null
            ? (int) $this->option('user')
            : (User::query()->value('id') !== null ? (int) User::query()->value('id') : null);

        $this->info('Fleeti historical import — imported_by='.($userId ?? 'system'));

        $summary = [];
        foreach ($this->argument('files') as $path) {
            if (! is_file($path)) {
                $this->error("File not found: {$path}");
                $summary[] = [basename($path), 'MISSING', '', '', ''];

                continue;
            }

            $this->line('Parsing '.basename($path).' …');
            $result = $service->import($path, $userId);

            $invalid = $result['invalid'] ?? [];
            $summary[] = [
                basename($path),
                count($result['valid'] ?? []),
                (int) ($result['inserted'] ?? 0),
                (int) ($result['updated'] ?? 0),
                count($invalid),
            ];

            foreach (array_slice($invalid, 0, 20) as $row) {
                $this->line('    ignored: '.($row['reason'] ?? 'raison inconnue'));
            }
            if (count($invalid) > 20) {
                $this->line('    … and '.(count($invalid) - 20).' more ignored row(s)');
            }
        }

        $this->newLine();
        $this->table(['File', 'Valid rows', 'Inserted', 'Updated', 'Ignored'], $summary);

        return self::SUCCESS;
    }
}
