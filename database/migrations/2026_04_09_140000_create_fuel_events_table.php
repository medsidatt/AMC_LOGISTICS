<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fuel_events')) {
            return;
        }

        Schema::create('fuel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();

            $table->string('event_type', 20); // 'refill', 'drop', 'theft_suspected'
            $table->decimal('litres_delta', 8, 2);  // positive for refill, negative for drop
            $table->decimal('litres_before', 8, 2);
            $table->decimal('litres_after', 8, 2);

            $table->decimal('odometer_km', 15, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('ignition_on')->nullable();

            $table->timestamp('detected_at');

            $table->foreignId('snapshot_before_id')
                ->nullable()
                ->constrained('truck_telemetry_snapshots')
                ->nullOnDelete();
            $table->foreignId('snapshot_after_id')
                ->nullable()
                ->constrained('truck_telemetry_snapshots')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['truck_id', 'detected_at'], 'fuel_events_truck_detected_idx');
            $table->index(['event_type', 'reviewed_at'], 'fuel_events_type_reviewed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_events');
    }
};
