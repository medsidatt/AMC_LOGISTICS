<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the fleet capacity target configurable on 3 levels:
 *  - Global default (FleetSetting)
 *  - Per-truck override (trucks.target_rotations_per_week)
 *  - Per-week from client_demand_plans (already in place, just consumed)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('fleet_settings', 'target_rotations_per_week')) {
                $table->unsignedTinyInteger('target_rotations_per_week')->default(3);
            }
            if (! Schema::hasColumn('fleet_settings', 'default_capacity_tonnage')) {
                $table->decimal('default_capacity_tonnage', 8, 2)->default(45);
            }
        });

        Schema::table('trucks', function (Blueprint $table) {
            if (! Schema::hasColumn('trucks', 'target_rotations_per_week')) {
                $table->unsignedTinyInteger('target_rotations_per_week')->nullable()->after('capacity_tonnage');
            }
        });

        // Sane defaults on the single FleetSetting row
        DB::table('fleet_settings')->where('id', 1)->update([
            'target_rotations_per_week' => 3,
            'default_capacity_tonnage' => 45,
        ]);
    }

    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            if (Schema::hasColumn('trucks', 'target_rotations_per_week')) {
                $table->dropColumn('target_rotations_per_week');
            }
        });

        Schema::table('fleet_settings', function (Blueprint $table) {
            foreach (['target_rotations_per_week', 'default_capacity_tonnage'] as $col) {
                if (Schema::hasColumn('fleet_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
