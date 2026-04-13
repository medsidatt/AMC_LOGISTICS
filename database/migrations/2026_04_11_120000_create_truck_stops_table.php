<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('truck_stops')) {
            return;
        }

        Schema::create('truck_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();

            $table->foreignId('start_snapshot_id')
                ->nullable()
                ->constrained('truck_telemetry_snapshots')
                ->nullOnDelete();
            $table->foreignId('end_snapshot_id')
                ->nullable()
                ->constrained('truck_telemetry_snapshots')
                ->nullOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();      // null while still open
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->boolean('ignition_was_off')->nullable();
            $table->decimal('fuel_litres_at_start', 8, 2)->nullable();
            $table->decimal('fuel_litres_at_end', 8, 2)->nullable();

            $table->foreignId('place_id')
                ->nullable()
                ->constrained('places')
                ->nullOnDelete();

            // 'known_base' | 'known_provider' | 'known_client' | 'known_fuel_station'
            // | 'unknown' | 'roadside'
            $table->string('classification', 20)->nullable();

            $table->timestamps();

            $table->index(['truck_id', 'started_at'], 'truck_stops_truck_started_idx');
            $table->index('place_id', 'truck_stops_place_idx');
            $table->index(['classification', 'ended_at'], 'truck_stops_class_ended_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_stops');
    }
};
