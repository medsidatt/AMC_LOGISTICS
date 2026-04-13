<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kilometer_trackings')) {
            return;
        }

        if (Schema::hasColumn('kilometer_trackings', 'telemetry_snapshot_id')) {
            return;
        }

        Schema::table('kilometer_trackings', function (Blueprint $table) {
            $table->foreignId('telemetry_snapshot_id')
                ->nullable()
                ->after('truck_id')
                ->constrained('truck_telemetry_snapshots')
                ->nullOnDelete();

            $table->index('telemetry_snapshot_id', 'km_trackings_snapshot_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kilometer_trackings')) {
            return;
        }

        if (! Schema::hasColumn('kilometer_trackings', 'telemetry_snapshot_id')) {
            return;
        }

        Schema::table('kilometer_trackings', function (Blueprint $table) {
            $table->dropForeign(['telemetry_snapshot_id']);
            $table->dropIndex('km_trackings_snapshot_idx');
            $table->dropColumn('telemetry_snapshot_id');
        });
    }
};
