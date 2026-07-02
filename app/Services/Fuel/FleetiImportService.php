<?php

namespace App\Services\Fuel;

use App\Models\FleetiDailyRecord;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;

/**
 * Single owner of Fleeti daily-record PERSISTENCE — the upsert previously inlined in
 * FuelImportController::commitFleeti, extracted so both the HTTP commit and the historical CLI
 * importer share ONE path (no duplication, no behaviour change). It adds no business rule: each row
 * already declares (via `_owned`, set by {@see FleetiFuelParser} from the export format) exactly
 * which columns its source is authoritative for, so importing both Fleeti exports for the same
 * (truck, date) stays deterministic and import-order independent.
 */
class FleetiImportService
{
    /** Every daily-record column a source may be authoritative for. */
    private const COLUMNS = [
        'kilometers', 'volume_initial', 'volume_final', 'consumed', 'consumed_per_100km',
        'refills_count', 'refills_volume', 'drains_count', 'drains_volume',
    ];

    public function __construct(private readonly FleetiFuelParser $parser) {}

    /**
     * Parse a Fleeti workbook and persist its valid daily rows. Returns the parse preview merged
     * with the persistence counts.
     *
     * @return array<string,mixed>
     */
    public function import(string $path, ?int $userId, ?string $readerType = null): array
    {
        $readerType ??= str_ends_with(strtolower($path), '.xls') ? Excel::XLS : Excel::XLSX;
        $preview = $this->parser->parse($path, $readerType);

        $counts = $this->persist($preview['valid'] ?? [], $userId);

        return $preview + $counts;
    }

    /**
     * Upsert already-parsed valid rows into fleeti_daily_records by (truck_id, record_date),
     * writing only the columns each row owns.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array{inserted:int, updated:int}
     */
    public function persist(array $rows, ?int $userId): array
    {
        $inserted = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $userId, &$inserted, &$updated) {
            foreach ($rows as $row) {
                $owned = $row['_owned'] ?? self::COLUMNS; // legacy safety: no marker → write all present
                $payload = ['imported_by' => $userId];
                foreach ($owned as $k) {
                    if (array_key_exists($k, $row)) {
                        $payload[$k] = $row[$k];
                    }
                }

                $existing = FleetiDailyRecord::query()
                    ->where('truck_id', $row['truck_id'])
                    ->where('record_date', $row['date'])
                    ->first();

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    FleetiDailyRecord::create(array_merge($payload, [
                        'truck_id' => $row['truck_id'],
                        'record_date' => $row['date'],
                    ]));
                    $inserted++;
                }
            }
        });

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
