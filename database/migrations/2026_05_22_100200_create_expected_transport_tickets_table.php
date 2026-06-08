<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bridges GPS loadings (a truck visibly loaded at a quarry) to the
 * TransportTracking ticket the driver/logistics is supposed to register.
 * Closes the under-ticketing gap (e.g. March 2026: 34 GPS CSE visits vs
 * 13 CSE tickets — see memory).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expected_transport_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_dispatch_id')->constrained('daily_dispatches')->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();

            $table->timestamp('loaded_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamp('deadline_at');

            // expected | matched | missing | dismissed
            $table->string('status', 16)->default('expected');

            $table->foreignId('transport_tracking_id')->nullable()
                ->constrained('transport_trackings')
                ->nullOnDelete();

            $table->string('dismissed_reason')->nullable();
            $table->foreignId('dismissed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['daily_dispatch_id', 'loaded_at'], 'ett_dispatch_loaded_unique');
            $table->index(['status', 'deadline_at'], 'ett_status_deadline_idx');
            $table->index(['truck_id', 'loaded_at'], 'ett_truck_loaded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expected_transport_tickets');
    }
};
