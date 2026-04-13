<?php

namespace App\Console\Commands;

use App\Models\TruckTelemetrySnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CompactTelemetrySnapshots extends Command
{
    protected $signature = 'telemetry:compact
        {--older-than= : Override the hourly-compact age in days (default from config)}
        {--daily-after= : Override the daily-compact age in days (default from config)}
        {--dry-run : Print what would be deleted without actually deleting}';

    protected $description = 'Compact truck_telemetry_snapshots: keep 1/hour after N days, 1/day after M days. Tracking rows are preserved — only raw snapshots are pruned.';

    public function handle(): int
    {
        $hourlyAfter = (int) ($this->option('older-than') ?? config('maintenance.telemetry_compact_hourly_after_days', 90));
        $dailyAfter = (int) ($this->option('daily-after') ?? config('maintenance.telemetry_compact_daily_after_days', 365));
        $dryRun = (bool) $this->option('dry-run');

        if ($dailyAfter < $hourlyAfter) {
            $this->error("daily-after ({$dailyAfter}) must be >= older-than ({$hourlyAfter}).");
            return self::FAILURE;
        }

        $now = now();
        $hourlyCutoff = $now->copy()->subDays($hourlyAfter);
        $dailyCutoff = $now->copy()->subDays($dailyAfter);

        $this->info("Compacting truck_telemetry_snapshots");
        $this->line("  - Keeping 1 per HOUR per truck for snapshots older than {$hourlyAfter} days ({$hourlyCutoff->toDateString()})");
        $this->line("  - Keeping 1 per DAY per truck for snapshots older than {$dailyAfter} days ({$dailyCutoff->toDateString()})");
        if ($dryRun) {
            $this->warn('DRY RUN — no rows will actually be deleted.');
        }

        // Pass 1: hourly buckets (between daily-cutoff and hourly-cutoff)
        $hourly = $this->compactBucket(
            'hourly',
            fromExclusive: $dailyCutoff,
            toInclusive: $hourlyCutoff,
            groupExpr: "truck_id, DATE_FORMAT(recorded_at, '%Y-%m-%d %H')",
            dryRun: $dryRun
        );

        // Pass 2: daily buckets (older than daily-cutoff)
        $daily = $this->compactBucket(
            'daily',
            fromExclusive: null,
            toInclusive: $dailyCutoff,
            groupExpr: "truck_id, DATE(recorded_at)",
            dryRun: $dryRun
        );

        $this->table(['Pass', 'Snapshots kept', 'Snapshots deleted'], [
            ['Hourly', $hourly['kept'], $hourly['deleted']],
            ['Daily', $daily['kept'], $daily['deleted']],
            ['TOTAL', $hourly['kept'] + $daily['kept'], $hourly['deleted'] + $daily['deleted']],
        ]);

        return self::SUCCESS;
    }

    /**
     * Keep the earliest snapshot per (truck_id, bucket) and delete the rest.
     * @return array{kept:int, deleted:int}
     */
    private function compactBucket(
        string $label,
        ?Carbon $fromExclusive,
        Carbon $toInclusive,
        string $groupExpr,
        bool $dryRun
    ): array {
        $query = TruckTelemetrySnapshot::query()
            ->where('recorded_at', '<=', $toInclusive);

        if ($fromExclusive) {
            $query->where('recorded_at', '>', $fromExclusive);
        }

        $totalInRange = $query->count();
        if ($totalInRange === 0) {
            $this->line("  [{$label}] nothing to compact in range.");
            return ['kept' => 0, 'deleted' => 0];
        }

        // Find the earliest snapshot ID per (truck_id, bucket)
        $keptIdsQuery = (clone $query)
            ->selectRaw("MIN(id) as id_to_keep")
            ->groupByRaw($groupExpr);

        $keptIds = $keptIdsQuery->pluck('id_to_keep')->filter()->map(fn ($v) => (int) $v)->all();
        $keptCount = count($keptIds);

        $deleteQuery = (clone $query);
        if (! empty($keptIds)) {
            $deleteQuery->whereNotIn('id', $keptIds);
        }

        $deleteCount = (clone $deleteQuery)->count();

        if ($dryRun) {
            $this->line("  [{$label}] would keep {$keptCount}, would delete {$deleteCount} (of {$totalInRange}).");
            return ['kept' => $keptCount, 'deleted' => $deleteCount];
        }

        // Delete in chunks to avoid huge transactions
        $deleted = 0;
        $deleteQuery->orderBy('id')->chunkById(1000, function ($rows) use (&$deleted) {
            $ids = $rows->pluck('id')->all();
            if (! empty($ids)) {
                DB::table('truck_telemetry_snapshots')->whereIn('id', $ids)->delete();
                $deleted += count($ids);
            }
        });

        $this->line("  [{$label}] kept {$keptCount}, deleted {$deleted} (of {$totalInRange}).");
        return ['kept' => $keptCount, 'deleted' => $deleted];
    }
}
