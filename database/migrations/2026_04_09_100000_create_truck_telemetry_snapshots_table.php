<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('truck_telemetry_snapshots')) {
            return;
        }

        Schema::create('truck_telemetry_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();

            // Timestamps
            $table->timestamp('recorded_at')->nullable();   // when the reading actually happened (Fleeti timestamp)
            $table->timestamp('synced_at')->useCurrent();     // when our sync fetched it
            $table->string('source', 30)->default('fleeti'); // fleeti, checklist, manual

            // Core counters
            $table->decimal('odometer_km', 15, 2)->nullable();
            $table->decimal('engine_hours', 10, 2)->nullable();
            $table->decimal('fuel_litres', 8, 2)->nullable();
            $table->decimal('speed_kmh', 6, 2)->nullable();

            // GPS
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('heading_deg', 5, 1)->nullable();
            $table->decimal('gps_accuracy_m', 6, 1)->nullable();

            // State
            $table->boolean('ignition_on')->nullable();
            $table->string('movement_status', 20)->nullable(); // 'moving', 'idle', 'parked', 'unknown'

            // Device health
            $table->decimal('battery_voltage', 5, 2)->nullable();
            $table->tinyInteger('signal_strength')->nullable();
            $table->timestamp('device_last_seen_at')->nullable();

            // Lossless capture — so we can extract NEW fields later without re-syncing
            $table->json('raw_payload')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['truck_id', 'recorded_at'], 'truck_telemetry_truck_recorded_idx');
            $table->index('recorded_at', 'truck_telemetry_recorded_idx');
            $table->index(['truck_id', 'synced_at'], 'truck_telemetry_truck_synced_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_telemetry_snapshots');
    }
};
