<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('theft_incidents')) {
            return;
        }

        Schema::create('theft_incidents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();

            $table->foreignId('transport_tracking_id')
                ->nullable()
                ->constrained('transport_trackings')
                ->nullOnDelete();
            $table->foreignId('trip_segment_id')
                ->nullable()
                ->constrained('trip_segments')
                ->nullOnDelete();
            $table->foreignId('truck_stop_id')
                ->nullable()
                ->constrained('truck_stops')
                ->nullOnDelete();
            $table->foreignId('fuel_event_id')
                ->nullable()
                ->constrained('fuel_events')
                ->nullOnDelete();

            // 'fuel_siphoning' | 'weight_gap' | 'unauthorized_stop'
            // | 'route_deviation' | 'off_hours_movement'
            $table->string('type', 30);
            // 'low' | 'medium' | 'high'
            $table->string('severity', 10)->default('medium');
            // 'pending' | 'reviewed' | 'dismissed' | 'confirmed'
            $table->string('status', 12)->default('pending');

            $table->timestamp('detected_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('title', 180);
            // Structured evidence: deltas, linked snapshot IDs, km gap, dedup_key, etc.
            $table->json('evidence')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['truck_id', 'detected_at'], 'theft_incidents_truck_detected_idx');
            $table->index(['status', 'severity', 'detected_at'], 'theft_incidents_status_sev_idx');
            $table->index(['type', 'status'], 'theft_incidents_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theft_incidents');
    }
};
