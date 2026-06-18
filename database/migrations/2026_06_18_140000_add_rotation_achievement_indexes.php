<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the rotation-achievement aggregations:
 *  - transport_trackings: period scans filter client_date and group by truck.
 *  - trip_segments: freight-loop scans filter ended_at per truck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->index(['client_date', 'truck_id'], 'tt_clientdate_truck_idx');
        });

        Schema::table('trip_segments', function (Blueprint $table) {
            $table->index(['truck_id', 'ended_at'], 'trip_segments_truck_ended_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->dropIndex('tt_clientdate_truck_idx');
        });

        Schema::table('trip_segments', function (Blueprint $table) {
            $table->dropIndex('trip_segments_truck_ended_idx');
        });
    }
};
