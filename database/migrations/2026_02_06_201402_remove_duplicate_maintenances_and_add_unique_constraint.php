<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Remove duplicate maintenances (keep only the first one per truck per date)
        // Get all duplicates: same truck_id and maintenance_date
        $duplicates = DB::table('maintenances')
            ->select('truck_id', 'maintenance_date', DB::raw('MIN(id) as keep_id'))
            ->groupBy('truck_id', 'maintenance_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Delete all records except the one we want to keep
            DB::table('maintenances')
                ->where('truck_id', $duplicate->truck_id)
                ->whereDate('maintenance_date', $duplicate->maintenance_date)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        // Step 2: Add unique constraint to prevent future duplicates
        Schema::table('maintenances', function (Blueprint $table) {
            $table->unique(['truck_id', 'maintenance_date'], 'maintenances_truck_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropUnique('maintenances_truck_date_unique');
        });
    }
};
