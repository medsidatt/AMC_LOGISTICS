<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trip_segments')) {
            return;
        }

        Schema::create('trip_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();

            $table->foreignId('transport_tracking_id')
                ->nullable()
                ->constrained('transport_trackings')
                ->cascadeOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at');

            $table->foreignId('start_snapshot_id')
                ->nullable()
                ->constrained('truck_telemetry_snapshots')
                ->nullOnDelete();
            $table->foreignId('end_snapshot_id')
                ->nullable()
                ->constrained('truck_telemetry_snapshots')
                ->nullOnDelete();

            $table->decimal('start_odometer_km', 15, 2)->nullable();
            $table->decimal('end_odometer_km', 15, 2)->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('fuel_consumed_litres', 8, 2)->nullable();

            $table->unsignedInteger('stop_count')->default(0);
            $table->unsignedInteger('unknown_stop_count')->default(0);

            $table->foreignId('origin_place_id')
                ->nullable()
                ->constrained('places')
                ->nullOnDelete();
            $table->foreignId('destination_place_id')
                ->nullable()
                ->constrained('places')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['truck_id', 'started_at'], 'trip_segments_truck_started_idx');
            $table->index('transport_tracking_id', 'trip_segments_transport_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_segments');
    }
};
