<?php

namespace App\Console\Commands;

use App\Domain\Operations\Parameters\FleetSettingParameterMap;
use App\Models\FleetSetting;
use App\Services\OperationalParameterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Synchronizes legacy FleetSetting values INTO the operational_parameters store so
 * OperationalParameter becomes the single source of truth without behaviour change.
 *
 * Idempotent. Never overwrites silently — every change is reported and logged.
 *   php artisan operations:sync-parameters [--dry-run]
 */
class SyncOperationalParameters extends Command
{
    protected $signature = 'operations:sync-parameters {--dry-run : Report differences without writing}';

    protected $description = 'Sync legacy FleetSetting values into the operational_parameters store (idempotent).';

    public function handle(OperationalParameterService $parameters): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $setting = FleetSetting::current();

        $rows = [];
        $changes = 0;

        foreach (FleetSettingParameterMap::map() as $field => $key) {
            $live = (float) ($setting->{$field} ?? 0);
            $current = $parameters->has($key) ? $parameters->float($key) : null;

            $inSync = $current !== null && abs($current - $live) < 1e-9;

            if ($inSync) {
                $rows[] = [$key->value, $this->fmt($current), $this->fmt($live), 'in-sync'];
                continue;
            }

            $action = $dryRun ? 'would-sync' : 'synced';
            if (! $dryRun) {
                $parameters->set($key, $live);
                Log::info('operations:sync-parameters synchronized a parameter', [
                    'parameter' => $key->value,
                    'from' => $current,
                    'to' => $live,
                    'source' => 'FleetSetting.'.$field,
                ]);
                $changes++;
            }

            $rows[] = [$key->value, $current === null ? '(missing)' : $this->fmt($current), $this->fmt($live), $action];
        }

        $this->table(['Parameter', 'Current', 'Live (FleetSetting)', 'Action'], $rows);

        if ($dryRun) {
            $this->info('Dry run — no parameters were written.');
        } else {
            $this->info($changes === 0 ? 'All parameters already in sync.' : "Synchronized {$changes} parameter(s).");
        }

        return self::SUCCESS;
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
    }
}
