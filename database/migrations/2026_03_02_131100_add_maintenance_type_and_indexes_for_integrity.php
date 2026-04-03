<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('maintenance_type', 50)->default('general')->after('maintenance_date');
            $table->index(['truck_id', 'maintenance_type', 'maintenance_date'], 'maintenances_truck_type_date_idx');
        });

        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropUnique('maintenances_truck_date_unique');
            $table->unique(['truck_id', 'maintenance_type', 'maintenance_date'], 'maintenances_truck_type_date_unique');
        });

        Schema::table('kilometer_trackings', function (Blueprint $table) {
            $table->index(['truck_id', 'date'], 'kilometer_trackings_truck_date_idx');
        });

        Schema::table('trucks', function (Blueprint $table) {
            $table->index(['maintenance_type', 'fleeti_last_synced_at'], 'trucks_maintenance_sync_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropIndex('trucks_maintenance_sync_idx');
        });

        Schema::table('kilometer_trackings', function (Blueprint $table) {
            $table->dropIndex('kilometer_trackings_truck_date_idx');
        });

        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropUnique('maintenances_truck_type_date_unique');
            $table->dropIndex('maintenances_truck_type_date_idx');
            $table->dropColumn('maintenance_type');
            $table->unique(['truck_id', 'maintenance_date'], 'maintenances_truck_date_unique');
        });
    }
};
