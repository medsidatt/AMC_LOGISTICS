<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucks')) {
            return;
        }

        Schema::table('trucks', function (Blueprint $table) {
            if (! Schema::hasColumn('trucks', 'fleeti_last_engine_hours')) {
                $table->decimal('fleeti_last_engine_hours', 10, 2)->nullable()->after('fleeti_last_fuel_level');
            }
            if (! Schema::hasColumn('trucks', 'fleeti_last_speed_kmh')) {
                $table->decimal('fleeti_last_speed_kmh', 6, 2)->nullable()->after('fleeti_last_engine_hours');
            }
            if (! Schema::hasColumn('trucks', 'fleeti_last_latitude')) {
                $table->decimal('fleeti_last_latitude', 10, 7)->nullable()->after('fleeti_last_speed_kmh');
            }
            if (! Schema::hasColumn('trucks', 'fleeti_last_longitude')) {
                $table->decimal('fleeti_last_longitude', 10, 7)->nullable()->after('fleeti_last_latitude');
            }
            if (! Schema::hasColumn('trucks', 'fleeti_last_heading_deg')) {
                $table->decimal('fleeti_last_heading_deg', 5, 1)->nullable()->after('fleeti_last_longitude');
            }
            if (! Schema::hasColumn('trucks', 'fleeti_last_ignition_on')) {
                $table->boolean('fleeti_last_ignition_on')->nullable()->after('fleeti_last_heading_deg');
            }
            if (! Schema::hasColumn('trucks', 'fleeti_last_movement_status')) {
                $table->string('fleeti_last_movement_status', 20)->nullable()->after('fleeti_last_ignition_on');
            }
            if (! Schema::hasColumn('trucks', 'fleeti_last_battery_voltage')) {
                $table->decimal('fleeti_last_battery_voltage', 5, 2)->nullable()->after('fleeti_last_movement_status');
            }
            if (! Schema::hasColumn('trucks', 'fleeti_last_signal_strength')) {
                $table->tinyInteger('fleeti_last_signal_strength')->nullable()->after('fleeti_last_battery_voltage');
            }
            if (! Schema::hasColumn('trucks', 'fleeti_device_last_seen_at')) {
                $table->timestamp('fleeti_device_last_seen_at')->nullable()->after('fleeti_last_signal_strength');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trucks')) {
            return;
        }

        Schema::table('trucks', function (Blueprint $table) {
            foreach ([
                'fleeti_last_engine_hours',
                'fleeti_last_speed_kmh',
                'fleeti_last_latitude',
                'fleeti_last_longitude',
                'fleeti_last_heading_deg',
                'fleeti_last_ignition_on',
                'fleeti_last_movement_status',
                'fleeti_last_battery_voltage',
                'fleeti_last_signal_strength',
                'fleeti_device_last_seen_at',
            ] as $col) {
                if (Schema::hasColumn('trucks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
