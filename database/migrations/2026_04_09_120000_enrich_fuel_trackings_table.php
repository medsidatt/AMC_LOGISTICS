<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fuel_trackings')) {
            return;
        }

        Schema::table('fuel_trackings', function (Blueprint $table) {
            if (! Schema::hasColumn('fuel_trackings', 'telemetry_snapshot_id')) {
                $table->foreignId('telemetry_snapshot_id')
                    ->nullable()
                    ->after('truck_id')
                    ->constrained('truck_telemetry_snapshots')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('fuel_trackings', 'engine_hours_at')) {
                $table->decimal('engine_hours_at', 10, 2)->nullable()->after('kilometers_at');
            }

            if (! Schema::hasColumn('fuel_trackings', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('engine_hours_at');
            }

            if (! Schema::hasColumn('fuel_trackings', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            if (! Schema::hasColumn('fuel_trackings', 'ignition_on')) {
                $table->boolean('ignition_on')->nullable()->after('longitude');
            }
        });

        // Add index separately to avoid failures if one column already existed.
        if (
            Schema::hasColumn('fuel_trackings', 'telemetry_snapshot_id')
            && ! $this->indexExists('fuel_trackings', 'fuel_trackings_snapshot_idx')
        ) {
            Schema::table('fuel_trackings', function (Blueprint $table) {
                $table->index('telemetry_snapshot_id', 'fuel_trackings_snapshot_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fuel_trackings')) {
            return;
        }

        if ($this->indexExists('fuel_trackings', 'fuel_trackings_snapshot_idx')) {
            Schema::table('fuel_trackings', function (Blueprint $table) {
                $table->dropIndex('fuel_trackings_snapshot_idx');
            });
        }

        Schema::table('fuel_trackings', function (Blueprint $table) {
            if (Schema::hasColumn('fuel_trackings', 'telemetry_snapshot_id')) {
                $table->dropForeign(['telemetry_snapshot_id']);
                $table->dropColumn('telemetry_snapshot_id');
            }
            foreach (['engine_hours_at', 'latitude', 'longitude', 'ignition_on'] as $col) {
                if (Schema::hasColumn('fuel_trackings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $dbName = Schema::getConnection()->getDatabaseName();
        $result = Schema::getConnection()->select(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$dbName, $table, $index]
        );
        return isset($result[0]) && (int) $result[0]->c > 0;
    }
};
