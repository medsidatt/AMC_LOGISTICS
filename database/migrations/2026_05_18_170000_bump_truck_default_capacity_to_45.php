<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business decision: each truck carries 45 t per loaded rotation
 * (was 25 t default). Updates both the column default for future
 * inserts and existing rows still on the old default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trucks', 'capacity_tonnage')) {
            return;
        }

        // Move every truck still on the old 25 t default to the new 45 t.
        // Trucks already customized (>0 and !=25) are left alone.
        DB::table('trucks')
            ->where(function ($q) {
                $q->whereNull('capacity_tonnage')
                  ->orWhere('capacity_tonnage', 25)
                  ->orWhere('capacity_tonnage', 0);
            })
            ->update(['capacity_tonnage' => 45]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('trucks', 'capacity_tonnage')) {
            return;
        }
        DB::table('trucks')->where('capacity_tonnage', 45)->update(['capacity_tonnage' => 25]);
    }
};
