<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent driver → truck assignment ("camion assigné"). Avoids scanning the
 * growing transport_trackings table just to find which truck a driver is on.
 * Backfilled from each driver's most recent tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('current_truck_id')->nullable()->after('user_id')
                ->constrained('trucks')->nullOnDelete();
        });

        foreach (DB::table('drivers')->pluck('id') as $driverId) {
            $truckId = DB::table('transport_trackings')
                ->where('driver_id', $driverId)
                ->whereNotNull('truck_id')
                ->orderByDesc('client_date')
                ->orderByDesc('id')
                ->value('truck_id');

            if ($truckId) {
                DB::table('drivers')->where('id', $driverId)->update(['current_truck_id' => $truckId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_truck_id');
        });
    }
};
